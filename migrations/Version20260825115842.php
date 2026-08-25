<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260825115842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // DEFAULT le temps de l'ALTER : sans lui, PostgreSQL refuse d'ajouter
        // une colonne NOT NULL à une table qui contient déjà des lignes. On le
        // retire ensuite, l'entité fournissant sa propre valeur par défaut.
        $this->addSql("ALTER TABLE parcours ADD description_audio JSON NOT NULL DEFAULT '{}'");
        $this->addSql('ALTER TABLE parcours ALTER COLUMN description_audio DROP DEFAULT');
        $this->addSql("ALTER TABLE parcours_point ADD audio JSON NOT NULL DEFAULT '{}'");
        $this->addSql('ALTER TABLE parcours_point ALTER COLUMN audio DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parcours DROP description_audio');
        $this->addSql('ALTER TABLE parcours_point DROP audio');
    }
}
