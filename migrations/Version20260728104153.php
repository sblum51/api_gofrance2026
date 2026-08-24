<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260728104153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Parcours.description (description/remarques, multilingue, facultative).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE parcours ADD description JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE parcours ALTER description DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parcours DROP description');
    }
}
