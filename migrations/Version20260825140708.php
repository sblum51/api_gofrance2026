<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260825140708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE membership (id UUID NOT NULL, role VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_86FFD285A76ED395 ON membership (user_id)');
        $this->addSql('CREATE INDEX IDX_86FFD28532C8A3DE ON membership (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_membership_user_organization ON membership (user_id, organization_id)');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD28532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE');
        // Report des propriétaires AVANT de supprimer la colonne : sans cette
        // ligne, chaque organisation existante perdrait son gestionnaire et
        // deviendrait inaccessible. gen_random_uuid() plutôt qu'un UUID v7 :
        // ces lignes-là n'ont pas besoin d'être ordonnables dans le temps.
        $this->addSql(<<<'SQL'
            INSERT INTO membership (id, user_id, organization_id, role, created_at)
            SELECT gen_random_uuid(), o.owner_id, o.id, 'owner', NOW()
            FROM organization o
            WHERE o.owner_id IS NOT NULL
            SQL);

        $this->addSql('ALTER TABLE organization DROP CONSTRAINT fk_c1ee637c7e3c61f9');
        $this->addSql('DROP INDEX uniq_c1ee637c7e3c61f9');
        $this->addSql('ALTER TABLE organization DROP owner_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE membership DROP CONSTRAINT FK_86FFD285A76ED395');
        $this->addSql('ALTER TABLE membership DROP CONSTRAINT FK_86FFD28532C8A3DE');
        $this->addSql('DROP TABLE membership');
        $this->addSql('ALTER TABLE organization ADD owner_id UUID NOT NULL');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT fk_c1ee637c7e3c61f9 FOREIGN KEY (owner_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_c1ee637c7e3c61f9 ON organization (owner_id)');
    }
}
