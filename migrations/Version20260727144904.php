<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727144904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE organization (id UUID NOT NULL, name VARCHAR(255) NOT NULL, identifier VARCHAR(63) NOT NULL, logo_name VARCHAR(255) DEFAULT NULL, logo_size INT DEFAULT NULL, cover_image_name VARCHAR(255) DEFAULT NULL, cover_image_size INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C1EE637C7E3C61F9 ON organization (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_organization_identifier ON organization (identifier)');
        $this->addSql('CREATE TABLE parcours (id UUID NOT NULL, name VARCHAR(255) NOT NULL, photo_name VARCHAR(255) DEFAULT NULL, photo_size INT DEFAULT NULL, distance_km DOUBLE PRECISION NOT NULL, duration_minutes INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_99B1DEE332C8A3DE ON parcours (organization_id)');
        $this->addSql('CREATE TABLE parcours_tag (parcours_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY (parcours_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_7932EC8C6E38C0DB ON parcours_tag (parcours_id)');
        $this->addSql('CREATE INDEX IDX_7932EC8CBAD26311 ON parcours_tag (tag_id)');
        $this->addSql('CREATE TABLE tag (id UUID NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tag_name ON tag (name)');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_verified BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON "user" (email)');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE parcours ADD CONSTRAINT FK_99B1DEE332C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE parcours_tag ADD CONSTRAINT FK_7932EC8C6E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parcours_tag ADD CONSTRAINT FK_7932EC8CBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organization DROP CONSTRAINT FK_C1EE637C7E3C61F9');
        $this->addSql('ALTER TABLE parcours DROP CONSTRAINT FK_99B1DEE332C8A3DE');
        $this->addSql('ALTER TABLE parcours_tag DROP CONSTRAINT FK_7932EC8C6E38C0DB');
        $this->addSql('ALTER TABLE parcours_tag DROP CONSTRAINT FK_7932EC8CBAD26311');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE parcours');
        $this->addSql('DROP TABLE parcours_tag');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE "user"');
    }
}
