<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260515210041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE match_sets (id UUID NOT NULL, validated_p1 BOOLEAN NOT NULL, validated_p2 BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, match_id UUID NOT NULL, round_id UUID NOT NULL, winner_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F5E2F4A32ABEACD6 ON match_sets (match_id)');
        $this->addSql('CREATE INDEX IDX_F5E2F4A3A6005CA0 ON match_sets (round_id)');
        $this->addSql('CREATE INDEX IDX_F5E2F4A35DFCD4B8 ON match_sets (winner_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F5E2F4A32ABEACD6A6005CA0 ON match_sets (match_id, round_id)');
        $this->addSql('CREATE TABLE matches (id UUID NOT NULL, bracket_round INT DEFAULT NULL, bracket_position INT DEFAULT NULL, board_number INT NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tournament_id UUID NOT NULL, pool_id UUID DEFAULT NULL, player1_id UUID NOT NULL, player2_id UUID NOT NULL, winner_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_62615BA33D1A3E7 ON matches (tournament_id)');
        $this->addSql('CREATE INDEX IDX_62615BA7B3406DF ON matches (pool_id)');
        $this->addSql('CREATE INDEX IDX_62615BAC0990423 ON matches (player1_id)');
        $this->addSql('CREATE INDEX IDX_62615BAD22CABCD ON matches (player2_id)');
        $this->addSql('CREATE INDEX IDX_62615BA5DFCD4B8 ON matches (winner_id)');
        $this->addSql('CREATE TABLE pool_players (id UUID NOT NULL, rank INT DEFAULT NULL, pool_id UUID NOT NULL, registration_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_312C759A7B3406DF ON pool_players (pool_id)');
        $this->addSql('CREATE INDEX IDX_312C759A833D8F43 ON pool_players (registration_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_312C759A7B3406DF833D8F43 ON pool_players (pool_id, registration_id)');
        $this->addSql('CREATE TABLE pools (id UUID NOT NULL, name VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tournament_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1F7A78B733D1A3E7 ON pools (tournament_id)');
        $this->addSql('CREATE TABLE registrations (id UUID NOT NULL, player_name VARCHAR(255) NOT NULL, player_email VARCHAR(255) NOT NULL, player_phone VARCHAR(50) DEFAULT NULL, player_names JSON DEFAULT NULL, stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, qr_code_token VARCHAR(64) NOT NULL, platform_fee_cents INT DEFAULT NULL, fee_collected BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tournament_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_53DE51E71BC9050B ON registrations (qr_code_token)');
        $this->addSql('CREATE INDEX IDX_53DE51E733D1A3E7 ON registrations (tournament_id)');
        $this->addSql('CREATE TABLE rounds (id UUID NOT NULL, round_order INT NOT NULL, game_type VARCHAR(10) NOT NULL, entry_type VARCHAR(10) NOT NULL, finish_type VARCHAR(10) NOT NULL, tournament_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3A7FD55433D1A3E7 ON rounds (tournament_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3A7FD55433D1A3E7235A8761 ON rounds (tournament_id, round_order)');
        $this->addSql('CREATE TABLE tournaments (id UUID NOT NULL, name VARCHAR(255) NOT NULL, date DATE NOT NULL, location VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, max_players INT NOT NULL, entry_fee INT NOT NULL, nb_pools INT NOT NULL, nb_boards INT NOT NULL, players_per_team INT NOT NULL, advancement_per_pool INT DEFAULT NULL, registration_mode VARCHAR(20) NOT NULL, scoring_mode VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E4BCFAC332C8A3DE ON tournaments (organization_id)');
        $this->addSql('ALTER TABLE match_sets ADD CONSTRAINT FK_F5E2F4A32ABEACD6 FOREIGN KEY (match_id) REFERENCES matches (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE match_sets ADD CONSTRAINT FK_F5E2F4A3A6005CA0 FOREIGN KEY (round_id) REFERENCES rounds (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE match_sets ADD CONSTRAINT FK_F5E2F4A35DFCD4B8 FOREIGN KEY (winner_id) REFERENCES registrations (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA33D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA7B3406DF FOREIGN KEY (pool_id) REFERENCES pools (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BAC0990423 FOREIGN KEY (player1_id) REFERENCES registrations (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BAD22CABCD FOREIGN KEY (player2_id) REFERENCES registrations (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA5DFCD4B8 FOREIGN KEY (winner_id) REFERENCES registrations (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pool_players ADD CONSTRAINT FK_312C759A7B3406DF FOREIGN KEY (pool_id) REFERENCES pools (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pool_players ADD CONSTRAINT FK_312C759A833D8F43 FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pools ADD CONSTRAINT FK_1F7A78B733D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE registrations ADD CONSTRAINT FK_53DE51E733D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rounds ADD CONSTRAINT FK_3A7FD55433D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournaments ADD CONSTRAINT FK_E4BCFAC332C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE match_sets DROP CONSTRAINT FK_F5E2F4A32ABEACD6');
        $this->addSql('ALTER TABLE match_sets DROP CONSTRAINT FK_F5E2F4A3A6005CA0');
        $this->addSql('ALTER TABLE match_sets DROP CONSTRAINT FK_F5E2F4A35DFCD4B8');
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BA33D1A3E7');
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BA7B3406DF');
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BAC0990423');
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BAD22CABCD');
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BA5DFCD4B8');
        $this->addSql('ALTER TABLE pool_players DROP CONSTRAINT FK_312C759A7B3406DF');
        $this->addSql('ALTER TABLE pool_players DROP CONSTRAINT FK_312C759A833D8F43');
        $this->addSql('ALTER TABLE pools DROP CONSTRAINT FK_1F7A78B733D1A3E7');
        $this->addSql('ALTER TABLE registrations DROP CONSTRAINT FK_53DE51E733D1A3E7');
        $this->addSql('ALTER TABLE rounds DROP CONSTRAINT FK_3A7FD55433D1A3E7');
        $this->addSql('ALTER TABLE tournaments DROP CONSTRAINT FK_E4BCFAC332C8A3DE');
        $this->addSql('DROP TABLE match_sets');
        $this->addSql('DROP TABLE matches');
        $this->addSql('DROP TABLE pool_players');
        $this->addSql('DROP TABLE pools');
        $this->addSql('DROP TABLE registrations');
        $this->addSql('DROP TABLE rounds');
        $this->addSql('DROP TABLE tournaments');
    }
}
