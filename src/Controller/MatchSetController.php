<?php

namespace App\Controller;

use App\Entity\DartsMatch;
use App\Entity\MatchSet;
use App\Entity\Registration;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Repository\DartsMatchRepository;
use App\Repository\MatchSetRepository;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public score endpoints — no JWT required.
 * Validation: winner must be one of the two players in the match.
 */
#[Route('/api/public/match-sets')]
class MatchSetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MatchSetRepository     $setRepo,
        private readonly DartsMatchRepository   $matchRepo,
        private readonly RegistrationRepository $registrationRepo,
        private readonly TournamentRepository   $tournamentRepo,
    ) {}

    // ── Propose winner (player proposes, other confirms) ──────────────────────

    #[Route('/{id}/propose', name: 'api_set_propose', methods: ['POST'])]
    public function propose(string $id, Request $request): JsonResponse
    {
        $set = $this->setRepo->find($id);
        if (!$set) return $this->json(['error' => 'Set introuvable.'], 404);
        if ($set->getMatch()->getStatus() === MatchStatus::FINISHED) {
            return $this->json(['error' => 'Ce match est déjà terminé.'], 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $winnerId   = (string) ($data['winnerId'] ?? '');
        $playerSide = (int) ($data['playerSide'] ?? 0);

        $winner = $this->validateWinner($set->getMatch(), $winnerId);
        if (!$winner) return $this->json(['error' => 'Gagnant invalide.'], 400);

        $set->setWinner($winner);
        if ($playerSide === 1) {
            $set->setValidatedP1(true);
            $set->setValidatedP2(false);
        } else {
            $set->setValidatedP2(true);
            $set->setValidatedP1(false);
        }

        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    // ── Confirm winner (other player confirms) ────────────────────────────────

    #[Route('/{id}/confirm', name: 'api_set_confirm', methods: ['POST'])]
    public function confirm(string $id, Request $request): JsonResponse
    {
        $set = $this->setRepo->find($id);
        if (!$set || !$set->getWinner()) return $this->json(['error' => 'Aucun résultat proposé pour ce set.'], 404);

        $data = json_decode($request->getContent(), true) ?? [];
        $playerSide = (int) ($data['playerSide'] ?? 0);

        if ($playerSide === 1) {
            $set->setValidatedP1(true);
        } else {
            $set->setValidatedP2(true);
        }

        $this->em->flush();

        // Check if all sets of the match are validated
        $match = $set->getMatch();
        $allSets = $this->setRepo->findBy(['match' => $match]);
        $allComplete = !empty($allSets) && array_reduce(
            $allSets,
            fn (bool $carry, MatchSet $s) => $carry && $s->isValidatedP1() && $s->isValidatedP2(),
            true
        );

        if ($allComplete) {
            $this->finalizeMatch($match, $allSets);
        }

        return $this->json(['ok' => true, 'matchFinished' => $allComplete]);
    }

    // ── Dispute result ────────────────────────────────────────────────────────

    #[Route('/{id}/dispute', name: 'api_set_dispute', methods: ['POST'])]
    public function dispute(string $id): JsonResponse
    {
        $set = $this->setRepo->find($id);
        if (!$set) return $this->json(['error' => 'Set introuvable.'], 404);

        $set->setWinner(null);
        $set->setValidatedP1(false);
        $set->setValidatedP2(false);

        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    // ── Direct mark (traditional scoring — admin validates both sides) ─────────

    #[Route('/{id}/mark', name: 'api_set_mark', methods: ['POST'])]
    public function mark(string $id, Request $request): JsonResponse
    {
        $set = $this->setRepo->find($id);
        if (!$set) return $this->json(['error' => 'Set introuvable.'], 404);
        if ($set->getMatch()->getStatus() === MatchStatus::FINISHED) {
            return $this->json(['error' => 'Ce match est déjà terminé.'], 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $winnerId = (string) ($data['winnerId'] ?? '');

        $winner = $this->validateWinner($set->getMatch(), $winnerId);
        if (!$winner) return $this->json(['error' => 'Gagnant invalide.'], 400);

        $set->setWinner($winner);
        $set->setValidatedP1(true);
        $set->setValidatedP2(true);

        $this->em->flush();

        $match = $set->getMatch();
        $allSets = $this->setRepo->findBy(['match' => $match]);
        $allComplete = !empty($allSets) && array_reduce(
            $allSets,
            fn (bool $carry, MatchSet $s) => $carry && $s->isValidatedP1() && $s->isValidatedP2(),
            true
        );

        if ($allComplete) {
            $this->finalizeMatch($match, $allSets);
        }

        return $this->json(['ok' => true, 'matchFinished' => $allComplete]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validateWinner(DartsMatch $match, string $winnerId): ?Registration
    {
        if ((string) $match->getPlayer1()->getId() === $winnerId) return $match->getPlayer1();
        if ((string) $match->getPlayer2()->getId() === $winnerId) return $match->getPlayer2();
        return null;
    }

    /** Called when all sets of a match are validated. Computes winner, activates next match, triggers bracket advance. */
    private function finalizeMatch(DartsMatch $match, array $sets): void
    {
        $p1Id = (string) $match->getPlayer1()->getId();
        $p1Wins = 0;
        $p2Wins = 0;
        foreach ($sets as $s) {
            if ($s->getWinner() === null) continue;
            if ((string) $s->getWinner()->getId() === $p1Id) $p1Wins++;
            else $p2Wins++;
        }

        $winner = $p1Wins >= $p2Wins ? $match->getPlayer1() : $match->getPlayer2();
        $match->setWinner($winner);
        $match->setStatus(MatchStatus::FINISHED);
        $this->em->flush();

        // Activate next PENDING match on the same board
        $this->activateNextMatchOnBoard($match);

        // Auto-advance bracket if this is a bracket match
        if ($match->getPool() === null && $match->getBracketRound() !== null) {
            $this->tryAdvanceBracket($match->getTournament(), $match->getBracketRound());
        }
    }

    private function activateNextMatchOnBoard(DartsMatch $finishedMatch): void
    {
        $nextMatch = $this->em->createQueryBuilder()
            ->select('m')
            ->from(DartsMatch::class, 'm')
            ->where('m.tournament = :t')
            ->andWhere('m.boardNumber = :board')
            ->andWhere('m.status = :status')
            ->setParameter('t', $finishedMatch->getTournament())
            ->setParameter('board', $finishedMatch->getBoardNumber())
            ->setParameter('status', MatchStatus::PENDING)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$nextMatch) return;

        $nextMatch->setStatus(MatchStatus::IN_PROGRESS);

        // Ensure match_sets exist for the newly activated match
        $rounds = $nextMatch->getTournament()->getRounds();
        if ($rounds->isEmpty()) {
            $this->em->flush();
            return;
        }

        foreach ($rounds as $round) {
            $existingSet = $this->setRepo->findOneBy(['match' => $nextMatch, 'round' => $round]);
            if (!$existingSet) {
                $set = new MatchSet();
                $set->setMatch($nextMatch);
                $set->setRound($round);
                $this->em->persist($set);
            }
        }

        $this->em->flush();
    }

    private function tryAdvanceBracket(\App\Entity\Tournament $tournament, int $bracketRound): void
    {
        // Count non-finished bracket matches in this round
        $remaining = (int) $this->em->createQuery(
            'SELECT COUNT(m.id) FROM App\Entity\DartsMatch m
             WHERE m.tournament = :t AND m.bracketRound = :br AND m.status != :finished AND m.pool IS NULL'
        )->setParameter('t', $tournament)
         ->setParameter('br', $bracketRound)
         ->setParameter('finished', MatchStatus::FINISHED)
         ->getSingleScalarResult();

        if ($remaining > 0) return;

        $currentMatches = $this->em->createQuery(
            'SELECT m FROM App\Entity\DartsMatch m
             WHERE m.tournament = :t AND m.bracketRound = :br AND m.pool IS NULL
             ORDER BY m.bracketPosition ASC'
        )->setParameter('t', $tournament)
         ->setParameter('br', $bracketRound)
         ->getResult();

        if (empty($currentMatches)) return;

        // Finale done — mark tournament FINISHED
        if (count($currentMatches) === 1) {
            $tournament->setStatus(TournamentStatus::FINISHED);
            $this->em->flush();
            return;
        }

        // Check if next round already exists
        $nextRoundExists = (int) $this->em->createQuery(
            'SELECT COUNT(m.id) FROM App\Entity\DartsMatch m
             WHERE m.tournament = :t AND m.bracketRound = :next AND m.pool IS NULL'
        )->setParameter('t', $tournament)
         ->setParameter('next', $bracketRound + 1)
         ->getSingleScalarResult();

        if ($nextRoundExists > 0) return;

        $rounds = $tournament->getRounds()->toArray();
        $nextRound = $bracketRound + 1;
        $boardCounter = 1;
        $nbBoards = $tournament->getNbBoards();

        for ($i = 0; $i < count($currentMatches); $i += 2) {
            $m1 = $currentMatches[$i];
            $m2 = $currentMatches[$i + 1] ?? null;
            if (!$m2 || !$m1->getWinner() || !$m2->getWinner()) break;

            $boardNum = (($boardCounter - 1) % $nbBoards) + 1;
            $isFirst  = $boardCounter <= $nbBoards;
            $boardCounter++;

            $newMatch = new DartsMatch();
            $newMatch->setTournament($tournament);
            $newMatch->setBracketRound($nextRound);
            $newMatch->setBracketPosition((int) floor($i / 2));
            $newMatch->setBoardNumber($boardNum);
            $newMatch->setPlayer1($m1->getWinner());
            $newMatch->setPlayer2($m2->getWinner());
            $newMatch->setStatus($isFirst ? MatchStatus::IN_PROGRESS : MatchStatus::PENDING);
            $this->em->persist($newMatch);

            foreach ($rounds as $round) {
                $set = new MatchSet();
                $set->setMatch($newMatch);
                $set->setRound($round);
                $this->em->persist($set);
            }
        }

        $this->em->flush();
    }
}
