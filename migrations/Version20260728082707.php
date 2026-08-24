<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260728082707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parcours_point (id UUID NOT NULL, position INT DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, name JSON NOT NULL, description JSON NOT NULL, datatourisme_id VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, parcours_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_CC841D2C6E38C0DB ON parcours_point (parcours_id)');
        $this->addSql('ALTER TABLE parcours_point ADD CONSTRAINT FK_CC841D2C6E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organization ADD main_commune VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ADD main_commune_lat DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ADD main_commune_lng DOUBLE PRECISION DEFAULT NULL');
        $this->addSql("ALTER TABLE parcours ADD route_type VARCHAR(20) NOT NULL DEFAULT 'ordered'");
        $this->addSql('ALTER TABLE parcours ALTER COLUMN route_type DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parcours_point DROP CONSTRAINT FK_CC841D2C6E38C0DB');
        $this->addSql('DROP TABLE parcours_point');
        $this->addSql('ALTER TABLE organization DROP main_commune');
        $this->addSql('ALTER TABLE organization DROP main_commune_lat');
        $this->addSql('ALTER TABLE organization DROP main_commune_lng');
        $this->addSql('ALTER TABLE parcours DROP route_type');
    }
}
