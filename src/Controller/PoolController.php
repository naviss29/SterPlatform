<?php

namespace App\Controller;

use App\Entity\DartsMatch;
use App\Entity\Pool;
use App\Entity\PoolPlayer;
use App\Entity\MatchSet;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Repository\DartsMatchRepository;
use App\Repository\PoolRepository;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentRepository;
use App\Security\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class PoolController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TournamentRepository   $tournamentRepo,
        private readonly RegistrationRepository $registrationRepo,
        private readonly DartsMatchRepository   $matchRepo,
        private readonly PoolRepository         $poolRepo,
    ) {}

    // ── Public: list pools with players + matches ─────────────────────────────

    #[Route('/public/tournaments/{id}/pools', name: 'api_public_pools_list', methods: ['GET'])]
    public function listPublic(string $id): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);

        return $this->json($this->serializePools($tournament));
    }

    // ── Auth: list pools ──────────────────────────────────────────────────────

    #[Route('/tournaments/{id}/pools', name: 'api_pools_list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $tournament->getOrganization());

        return $this->json($this->serializePools($tournament));
    }

    // ── Generate pools (receives pre-computed data from DartsOpen) ────────────

    #[Route('/tournaments/{id}/pools/generate', name: 'api_pools_generate', methods: ['POST'])]
    public function generate(string $id, Request $request): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        if ($tournament->getStatus() !== TournamentStatus::OPEN) {
            return $this->json(['error' => 'Les poules ne peuvent être générées que lorsque le tournoi est ouvert.'], 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $poolsData   = $data['pools'] ?? [];
        $matchesData = $data['matches'] ?? [];

        if (empty($poolsData)) return $this->json(['error' => 'Aucune poule fournie.'], 400);

        // Fetch all rounds for match_sets creation
        $rounds = $tournament->getRounds()->toArray();

        // 1. Delete existing pool matches (SET NULL would leave orphan matches)
        $existingPoolMatches = $this->em->createQuery(
            'DELETE FROM App\Entity\DartsMatch m WHERE m.tournament = :t AND m.pool IS NOT NULL'
        )->setParameter('t', $tournament)->execute();

        // 2. Delete existing pools (cascade: pool_players)
        $existingPools = $this->poolRepo->findBy(['tournament' => $tournament]);
        foreach ($existingPools as $pool) {
            $this->em->remove($pool);
        }
        $this->em->flush();

        // 3. Create pools with players
        $createdPools = [];
        foreach ($poolsData as $poolData) {
            $pool = new Pool();
            $pool->setTournament($tournament);
            $pool->setName((string) ($poolData['name'] ?? 'Poule'));
            $this->em->persist($pool);

            foreach ($poolData['playerIds'] as $registrationId) {
                $reg = $this->registrationRepo->find($registrationId);
                if (!$reg) continue;

                $pp = new PoolPlayer();
                $pp->setPool($pool);
                $pp->setRegistration($reg);
                $this->em->persist($pp);
            }

            $createdPools[] = $pool;
        }
        $this->em->flush();

        // 4. Create matches
        foreach ($matchesData as $matchData) {
            $poolIndex = (int) ($matchData['poolIndex'] ?? 0);
            $pool = $createdPools[$poolIndex] ?? null;
            if (!$pool) continue;

            $p1 = $this->registrationRepo->find($matchData['player1Id']);
            $p2 = $this->registrationRepo->find($matchData['player2Id']);
            if (!$p1 || !$p2) continue;

            $status = MatchStatus::tryFrom(strtoupper((string) ($matchData['status'] ?? 'PENDING')))
                ?? MatchStatus::PENDING;

            $match = new DartsMatch();
            $match->setTournament($tournament);
            $match->setPool($pool);
            $match->setPlayer1($p1);
            $match->setPlayer2($p2);
            $match->setBoardNumber((int) ($matchData['boardNumber'] ?? 1));
            $match->setStatus($status);
            $this->em->persist($match);

            // Create match_sets (one per round)
            foreach ($rounds as $round) {
                $set = new MatchSet();
                $set->setMatch($match);
                $set->setRound($round);
                $this->em->persist($set);
            }
        }

        // 5. Set tournament to IN_PROGRESS
        $tournament->setStatus(TournamentStatus::IN_PROGRESS);
        $this->em->flush();

        return $this->json(['message' => 'Poules générées avec succès.'], 201);
    }

    // ── Delete all pools ──────────────────────────────────────────────────────

    #[Route('/tournaments/{id}/pools', name: 'api_pools_delete_all', methods: ['DELETE'])]
    public function deleteAll(string $id): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        // Delete pool matches first
        $this->em->createQuery(
            'DELETE FROM App\Entity\DartsMatch m WHERE m.tournament = :t AND m.pool IS NOT NULL'
        )->setParameter('t', $tournament)->execute();

        $pools = $this->poolRepo->findBy(['tournament' => $tournament]);
        foreach ($pools as $pool) {
            $this->em->remove($pool);
        }
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Serializer ────────────────────────────────────────────────────────────

    private function serializePools(\App\Entity\Tournament $tournament): array
    {
        $pools = $this->poolRepo->findBy(['tournament' => $tournament], ['name' => 'ASC']);

        return array_map(function (Pool $pool) {
            $players = array_map(fn (PoolPlayer $pp) => [
                'id'         => (string) $pp->getRegistration()->getId(),
                'playerName' => $pp->getRegistration()->getPlayerName(),
            ], $pool->getPlayers()->toArray());

            $matches = $this->matchRepo->findBy(['pool' => $pool], ['boardNumber' => 'ASC']);
            $matchesData = array_map(fn (DartsMatch $m) => [
                'id'          => (string) $m->getId(),
                'status'      => $m->getStatus()->value,
                'boardNumber' => $m->getBoardNumber(),
                'player1'     => ['id' => (string) $m->getPlayer1()->getId(), 'player_name' => $m->getPlayer1()->getPlayerName()],
                'player2'     => ['id' => (string) $m->getPlayer2()->getId(), 'player_name' => $m->getPlayer2()->getPlayerName()],
                'winner_id'   => $m->getWinner() ? (string) $m->getWinner()->getId() : null,
            ], $matches);

            return [
                'id'      => (string) $pool->getId(),
                'name'    => $pool->getName(),
                'players' => $players,
                'matches' => $matchesData,
            ];
        }, $pools);
    }
}
