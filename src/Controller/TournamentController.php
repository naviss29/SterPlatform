<?php

namespace App\Controller;

use App\Entity\Round;
use App\Entity\Tournament;
use App\Enum\EntryType;
use App\Enum\FinishType;
use App\Enum\GameType;
use App\Enum\RegistrationMode;
use App\Enum\RegistrationStatus;
use App\Enum\ScoringMode;
use App\Enum\TournamentStatus;
use App\Repository\OrganizationMemberRepository;
use App\Repository\OrganizationRepository;
use App\Repository\RegistrationRepository;
use App\Repository\RoundRepository;
use App\Repository\TournamentRepository;
use App\Security\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class TournamentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface       $em,
        private readonly TournamentRepository         $tournamentRepo,
        private readonly RoundRepository              $roundRepo,
        private readonly RegistrationRepository       $registrationRepo,
        private readonly OrganizationRepository       $organizationRepo,
        private readonly OrganizationMemberRepository $memberRepo,
    ) {}

    // ── List ──────────────────────────────────────────────────────────────────

    #[Route('/organizations/{slug}/tournaments', name: 'api_tournaments_list', methods: ['GET'])]
    public function list(string $slug): JsonResponse
    {
        $org = $this->organizationRepo->findBySlug($slug);
        if (!$org) return $this->json(['error' => 'Organisation introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $org);

        $tournaments = $this->tournamentRepo->findByOrganization($org);

        return $this->json(array_map(
            fn (Tournament $t) => $this->serializeTournamentSummary($t),
            $tournaments
        ));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    #[Route('/organizations/{slug}/tournaments', name: 'api_tournaments_create', methods: ['POST'])]
    public function create(string $slug, Request $request): JsonResponse
    {
        $org = $this->organizationRepo->findBySlug($slug);
        if (!$org) return $this->json(['error' => 'Organisation introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $org);

        $data = json_decode($request->getContent(), true) ?? [];
        [$tournament, $error] = $this->buildTournament($data);
        if ($error) return $this->json(['error' => $error], 400);

        $tournament->setOrganization($org);
        $this->em->persist($tournament);
        $this->em->flush();

        return $this->json($this->serializeTournament($tournament), 201);
    }

    // ── Get ───────────────────────────────────────────────────────────────────

    #[Route('/tournaments/{id}', name: 'api_tournaments_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $tournament = $this->tournamentRepo->findWithRounds($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $tournament->getOrganization());

        return $this->json($this->serializeTournament($tournament));
    }

    // Public get (for live page / score page)
    #[Route('/public/tournaments/{id}', name: 'api_public_tournaments_get', methods: ['GET'])]
    public function getPublic(string $id): JsonResponse
    {
        $tournament = $this->tournamentRepo->findWithRounds($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);

        return $this->json($this->serializeTournament($tournament));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    #[Route('/tournaments/{id}', name: 'api_tournaments_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        $data = json_decode($request->getContent(), true) ?? [];
        $error = $this->applyTournamentData($tournament, $data);
        if ($error) return $this->json(['error' => $error], 400);

        $this->em->flush();

        return $this->json($this->serializeTournament($tournament));
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    #[Route('/tournaments/{id}', name: 'api_tournaments_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        $this->em->remove($tournament);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Status ────────────────────────────────────────────────────────────────

    #[Route('/tournaments/{id}/status', name: 'api_tournaments_status', methods: ['PATCH'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        $data = json_decode($request->getContent(), true) ?? [];
        $statusValue = strtoupper((string) ($data['status'] ?? ''));
        $status = TournamentStatus::tryFrom($statusValue);
        if (!$status) return $this->json(['error' => 'Statut invalide.'], 400);

        if ($status === TournamentStatus::IN_PROGRESS) {
            $paid = $this->registrationRepo->findPaidByTournament($tournament);
            if (count($paid) < 2) {
                return $this->json(['error' => 'Il faut au moins 2 joueurs inscrits pour démarrer le tournoi.'], 400);
            }
        }

        $tournament->setStatus($status);
        $this->em->flush();

        return $this->json(['status' => $status->value]);
    }

    // ── Rounds ────────────────────────────────────────────────────────────────

    #[Route('/tournaments/{id}/rounds', name: 'api_tournaments_rounds_add', methods: ['POST'])]
    public function addRound(string $id, Request $request): JsonResponse
    {
        $tournament = $this->tournamentRepo->findWithRounds($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        $data = json_decode($request->getContent(), true) ?? [];

        $gameType = GameType::tryFrom(strtoupper((string) ($data['game_type'] ?? '')));
        $entryType = EntryType::tryFrom(strtoupper((string) ($data['entry_type'] ?? '')));
        $finishType = FinishType::tryFrom(strtoupper((string) ($data['finish_type'] ?? '')));

        if (!$gameType || !$entryType || !$finishType) {
            return $this->json(['error' => 'Types de manche invalides.'], 400);
        }

        $nextOrder = count($tournament->getRounds()) + 1;

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundOrder($nextOrder);
        $round->setGameType($gameType);
        $round->setEntryType($entryType);
        $round->setFinishType($finishType);

        $this->em->persist($round);
        $this->em->flush();

        return $this->json([
            'id'         => (string) $round->getId(),
            'order'      => $round->getRoundOrder(),
            'game_type'  => $round->getGameType()->value,
            'entry_type' => $round->getEntryType()->value,
            'finish_type' => $round->getFinishType()->value,
        ], 201);
    }

    #[Route('/tournaments/{id}/rounds/{roundId}', name: 'api_tournaments_rounds_delete', methods: ['DELETE'])]
    public function deleteRound(string $id, string $roundId): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        $round = $this->roundRepo->find($roundId);
        if (!$round || $round->getTournament()->getId() != $tournament->getId()) {
            return $this->json(['error' => 'Manche introuvable.'], 404);
        }

        $this->em->remove($round);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Registrations ─────────────────────────────────────────────────────────

    #[Route('/tournaments/{id}/registrations', name: 'api_tournaments_registrations_list', methods: ['GET'])]
    public function listRegistrations(string $id, Request $request): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $tournament->getOrganization());

        $statusFilter = $request->query->get('status');

        if ($statusFilter === 'PAID') {
            $registrations = $this->registrationRepo->findPaidByTournament($tournament);
        } else {
            $registrations = $this->registrationRepo->findBy(
                ['tournament' => $tournament],
                ['createdAt' => 'ASC']
            );
        }

        return $this->json(array_map(
            fn ($r) => $this->serializeRegistration($r),
            $registrations
        ));
    }

    #[Route('/tournaments/{id}/registrations', name: 'api_tournaments_registrations_create', methods: ['POST'])]
    public function addRegistration(string $id, Request $request): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        if (!in_array($tournament->getStatus(), [TournamentStatus::DRAFT, TournamentStatus::OPEN], true)) {
            return $this->json(['error' => 'Les inscriptions sont fermées pour ce tournoi.'], 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $playerName  = trim((string) ($data['playerName'] ?? ''));
        $playerEmail = trim((string) ($data['playerEmail'] ?? ''));
        $playerPhone = trim((string) ($data['playerPhone'] ?? '')) ?: null;
        $playerNames = $data['playerNames'] ?? null;
        $platformFeeCents = (int) ($data['platformFeeCents'] ?? 0);

        if (strlen($playerName) < 2) return $this->json(['error' => 'Nom trop court.'], 400);
        if (!filter_var($playerEmail, FILTER_VALIDATE_EMAIL)) return $this->json(['error' => 'Email invalide.'], 400);

        $reg = new \App\Entity\Registration();
        $reg->setTournament($tournament);
        $reg->setPlayerName($playerName);
        $reg->setPlayerEmail($playerEmail);
        $reg->setPlayerPhone($playerPhone);
        $reg->setPlayerNames(is_array($playerNames) ? $playerNames : null);
        $reg->setStatus(RegistrationStatus::PAID);
        $reg->setPlatformFeeCents($platformFeeCents ?: null);
        $reg->setFeeCollected(false);

        $this->em->persist($reg);
        $this->em->flush();

        return $this->json($this->serializeRegistration($reg), 201);
    }

    #[Route('/tournaments/{id}/registrations/{rid}', name: 'api_tournaments_registrations_delete', methods: ['DELETE'])]
    public function deleteRegistration(string $id, string $rid): JsonResponse
    {
        $tournament = $this->tournamentRepo->find($id);
        if (!$tournament) return $this->json(['error' => 'Tournoi introuvable.'], 404);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $tournament->getOrganization());

        if (!in_array($tournament->getStatus(), [TournamentStatus::DRAFT, TournamentStatus::OPEN], true)) {
            return $this->json(['error' => 'Impossible de retirer un joueur une fois le tournoi démarré.'], 400);
        }

        $reg = $this->registrationRepo->find($rid);
        if (!$reg || $reg->getTournament()->getId() != $tournament->getId()) {
            return $this->json(['error' => 'Inscription introuvable.'], 404);
        }

        $this->em->remove($reg);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Serializers ───────────────────────────────────────────────────────────

    private function serializeTournamentSummary(Tournament $t): array
    {
        return [
            'id'           => (string) $t->getId(),
            'name'         => $t->getName(),
            'date'         => $t->getDate()->format('Y-m-d'),
            'location'     => $t->getLocation(),
            'status'       => $t->getStatus()->value,
            'maxPlayers'   => $t->getMaxPlayers(),
            'entryFee'     => $t->getEntryFee(),
            'nbPools'      => $t->getNbPools(),
            'nbBoards'     => $t->getNbBoards(),
            'playersPerTeam' => $t->getPlayersPerTeam(),
            'advancementPerPool' => $t->getAdvancementPerPool(),
            'registrationMode' => $t->getRegistrationMode()->value,
            'scoringMode'  => $t->getScoringMode()->value,
            'createdAt'    => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeTournament(Tournament $t): array
    {
        $data = $this->serializeTournamentSummary($t);
        $data['rounds'] = array_map(
            fn (Round $r) => [
                'id'          => (string) $r->getId(),
                'order'       => $r->getRoundOrder(),
                'game_type'   => $r->getGameType()->value,
                'entry_type'  => $r->getEntryType()->value,
                'finish_type' => $r->getFinishType()->value,
            ],
            $t->getRounds()->toArray()
        );
        usort($data['rounds'], fn ($a, $b) => $a['order'] <=> $b['order']);
        return $data;
    }

    private function serializeRegistration(\App\Entity\Registration $r): array
    {
        return [
            'id'                => (string) $r->getId(),
            'playerName'        => $r->getPlayerName(),
            'playerEmail'       => $r->getPlayerEmail(),
            'playerPhone'       => $r->getPlayerPhone(),
            'playerNames'       => $r->getPlayerNames(),
            'status'            => $r->getStatus()->value,
            'platformFeeCents'  => $r->getPlatformFeeCents(),
            'feeCollected'      => $r->isFeeCollected(),
            'qrCodeToken'       => $r->getQrCodeToken(),
            'createdAt'         => $r->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    // ── Builders ──────────────────────────────────────────────────────────────

    /** @return array{Tournament|null, string|null} */
    private function buildTournament(array $data): array
    {
        $tournament = new Tournament();
        $error = $this->applyTournamentData($tournament, $data);
        return [$tournament, $error];
    }

    private function applyTournamentData(Tournament $t, array $data): ?string
    {
        $name = trim((string) ($data['name'] ?? ''));
        if (strlen($name) < 3) return 'Le nom doit contenir au moins 3 caractères.';
        $t->setName($name);

        $dateStr = (string) ($data['date'] ?? '');
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if (!$date) return 'Date invalide (format attendu : YYYY-MM-DD).';
        $t->setDate($date);

        $location = trim((string) ($data['location'] ?? ''));
        if (strlen($location) < 2) return 'Le lieu doit contenir au moins 2 caractères.';
        $t->setLocation($location);

        $maxPlayers = (int) ($data['max_players'] ?? 0);
        if ($maxPlayers < 2 || $maxPlayers > 512) return 'Nombre de joueurs invalide (2–512).';
        $t->setMaxPlayers($maxPlayers);

        $entryFee = (int) ($data['entry_fee'] ?? 0);
        if ($entryFee < 0) return 'Les frais d\'inscription ne peuvent pas être négatifs.';
        $t->setEntryFee($entryFee);

        $nbPools = (int) ($data['nb_pools'] ?? 1);
        if ($nbPools < 1 || $nbPools > 64) return 'Nombre de poules invalide (1–64).';
        $t->setNbPools($nbPools);

        $nbBoards = (int) ($data['nb_boards'] ?? 1);
        if ($nbBoards < 1 || $nbBoards > 32) return 'Nombre de cibles invalide (1–32).';
        $t->setNbBoards($nbBoards);

        $playersPerTeam = (int) ($data['players_per_team'] ?? 1);
        if ($playersPerTeam < 1 || $playersPerTeam > 10) return 'Joueurs par équipe invalide (1–10).';
        $t->setPlayersPerTeam($playersPerTeam);

        $advancementPerPool = isset($data['advancement_per_pool']) ? (int) $data['advancement_per_pool'] : null;
        if ($advancementPerPool !== null && ($advancementPerPool < 1 || $advancementPerPool > 8)) {
            return 'Qualifiés par poule invalide (1–8).';
        }
        $t->setAdvancementPerPool($advancementPerPool);

        $regModeValue = strtoupper((string) ($data['registration_mode'] ?? 'ONLINE'));
        $regMode = RegistrationMode::tryFrom($regModeValue);
        if (!$regMode) return 'Mode d\'inscription invalide.';
        $t->setRegistrationMode($regMode);

        $scoreModeValue = strtoupper((string) ($data['scoring_mode'] ?? 'ELECTRONIC'));
        $scoreMode = ScoringMode::tryFrom($scoreModeValue);
        if (!$scoreMode) return 'Mode de saisie invalide.';
        $t->setScoringMode($scoreMode);

        return null;
    }
}
