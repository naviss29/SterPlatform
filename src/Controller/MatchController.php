<?php

namespace App\Controller;

use App\Entity\DartsMatch;
use App\Entity\MatchSet;
use App\Enum\MatchStatus;
use App\Repository\DartsMatchRepository;
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
class MatchController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TournamentRepository   $tournamentRepo,
        private readonly DartsMatchRepository   $matchRepo,
        private readonly RegistrationRepository $registrationRepo,
    ) {}

    // ── Public: live matches ──────────────────────────────────────────────────

    #[Route('/public/tournaments/{id}/matches', name: 'api_public_matches_list', methods: ['GET'])]
    public function listPublic(string $id, Request $request): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);

        return $this->json($this->fetchMatches($tournament, $request));
    }

    // ── Auth: match list ──────────────────────────────────────────────────────

    #[Route('/tournaments/{id}/matches', name: 'api_matches_list', methods: ['GET'])]
    public function list(string $id, Request $request): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $tournament->getOrganization());

        return $this->json($this->fetchMatches($tournament, $request));
    }

    // ── Bulk create matches (bracket) ─────────────────────────────────────────

    #[Route('/tournaments/{id}/matches/bulk', name: 'api_matches_bulk_create', methods: ['POST'])]
    public function bulkCreate(string $id, Request $request): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        $data = json_decode($request->getContent(), true) ?? [];
        $matchesData = $data['matches'] ?? [];

        $rounds = $tournament->getRounds()->toArray();
        $created = [];

        foreach ($matchesData as $md) {
            $p1 = $this->registrationRepo->find($md['player1Id'] ?? '');
            $p2 = $this->registrationRepo->find($md['player2Id'] ?? '');
            if (!$p1 || !$p2) continue;

            $status = MatchStatus::tryFrom(strtoupper((string) ($md['status'] ?? 'PENDING')))
                ?? MatchStatus::PENDING;

            $match = new DartsMatch();
            $match->setTournament($tournament);
            $match->setBracketRound((int) ($md['bracketRound'] ?? 1));
            $match->setBracketPosition((int) ($md['bracketPosition'] ?? 0));
            $match->setBoardNumber((int) ($md['boardNumber'] ?? 1));
            $match->setPlayer1($p1);
            $match->setPlayer2($p2);
            $match->setStatus($status);

            if (!empty($md['winnerId'])) {
                $winner = $this->registrationRepo->find($md['winnerId']);
                if ($winner) $match->setWinner($winner);
            }

            $this->em->persist($match);

            if ($status !== MatchStatus::FINISHED) {
                foreach ($rounds as $round) {
                    $set = new MatchSet();
                    $set->setMatch($match);
                    $set->setRound($round);
                    $this->em->persist($set);
                }
            }

            $created[] = $match;
        }

        $this->em->flush();

        return $this->json(array_map(fn ($m) => ['id' => (string) $m->getId()], $created), 201);
    }

    // ── Delete bracket matches ────────────────────────────────────────────────

    #[Route('/tournaments/{id}/matches/bracket', name: 'api_matches_bracket_delete', methods: ['DELETE'])]
    public function deleteBracket(string $id): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        $this->em->createQuery(
            'DELETE FROM App\Entity\DartsMatch m WHERE m.tournament = :t AND m.pool IS NULL'
        )->setParameter('t', $tournament)->execute();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Update match status ───────────────────────────────────────────────────

    #[Route('/matches/{id}/status', name: 'api_match_status', methods: ['PATCH'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $match = $this->matchRepo->find($id);
        if (!$match) return $this->json(['error' => 'Match introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $match->getOrganization());

        $data = json_decode($request->getContent(), true) ?? [];
        $status = MatchStatus::tryFrom(strtoupper((string) ($data['status'] ?? '')));
        if (!$status) return $this->json(['error' => 'Statut invalide.'], 400);

        $match->setStatus($status);
        $this->em->flush();

        return $this->json(['status' => $status->value]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function fetchMatches(\App\Entity\Tournament $tournament, Request $request): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('m', 'p1', 'p2', 'w', 's', 'r')
            ->from(DartsMatch::class, 'm')
            ->leftJoin('m.player1', 'p1')
            ->leftJoin('m.player2', 'p2')
            ->leftJoin('m.winner', 'w')
            ->leftJoin('m.sets', 's')
            ->leftJoin('s.round', 'r')
            ->where('m.tournament = :t')
            ->setParameter('t', $tournament)
            ->orderBy('m.boardNumber', 'ASC');

        // Filter by bracket_round
        if ($request->query->has('bracket_round')) {
            $qb->andWhere('m.bracketRound = :br')
               ->setParameter('br', (int) $request->query->get('bracket_round'));
        }

        // Filter: only bracket matches (no pool)
        if ($request->query->get('pool') === 'null') {
            $qb->andWhere('m.pool IS NULL');
        }

        $matches = $qb->getQuery()->getResult();

        return array_map(fn (DartsMatch $m) => $this->serializeMatch($m), $matches);
    }

    private function serializeMatch(DartsMatch $m): array
    {
        $sets = array_map(fn (MatchSet $s) => [
            'id'          => (string) $s->getId(),
            'roundId'     => (string) $s->getRound()->getId(),
            'roundOrder'  => $s->getRound()->getRoundOrder(),
            'winner_id'   => $s->getWinner() ? (string) $s->getWinner()->getId() : null,
            'validatedP1' => $s->isValidatedP1(),
            'validatedP2' => $s->isValidatedP2(),
        ], $m->getSets()->toArray());

        usort($sets, fn ($a, $b) => $a['roundOrder'] <=> $b['roundOrder']);

        return [
            'id'              => (string) $m->getId(),
            'status'          => $m->getStatus()->value,
            'boardNumber'     => $m->getBoardNumber(),
            'bracketRound'    => $m->getBracketRound(),
            'bracketPosition' => $m->getBracketPosition(),
            'pool_id'         => $m->getPool() ? (string) $m->getPool()->getId() : null,
            'player1'         => ['id' => (string) $m->getPlayer1()->getId(), 'player_name' => $m->getPlayer1()->getPlayerName()],
            'player2'         => ['id' => (string) $m->getPlayer2()->getId(), 'player_name' => $m->getPlayer2()->getPlayerName()],
            'winner_id'       => $m->getWinner() ? (string) $m->getWinner()->getId() : null,
            'sets'            => $sets,
        ];
    }
}
