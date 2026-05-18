<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create email_templates table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_templates (id UUID NOT NULL, slug VARCHAR(100) NOT NULL, project VARCHAR(50) DEFAULT NULL, subject VARCHAR(255) NOT NULL, html_body TEXT NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EMAIL_TEMPLATES_SLUG ON email_templates (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_templates');
    }
}
