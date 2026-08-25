<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260825141746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE membership DROP role');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // DEFAULT le temps de l'ALTER : la table contient des lignes, une
        // colonne NOT NULL sans valeur par défaut serait refusée.
        $this->addSql("ALTER TABLE membership ADD role VARCHAR(20) NOT NULL DEFAULT 'owner'");
        $this->addSql('ALTER TABLE membership ALTER COLUMN role DROP DEFAULT');
    }
}
