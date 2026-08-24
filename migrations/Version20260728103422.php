<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728103422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Départ (description+GPS) sur Parcours ; kind/media/links sur ParcoursPoint, websiteUrl replié dans links.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE parcours ADD depart_description JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE parcours ALTER depart_description DROP DEFAULT');
        $this->addSql('ALTER TABLE parcours ADD depart_latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE parcours ADD depart_longitude DOUBLE PRECISION DEFAULT NULL');

        $this->addSql("ALTER TABLE parcours_point ADD kind VARCHAR(20) DEFAULT 'route' NOT NULL");
        $this->addSql('ALTER TABLE parcours_point ALTER kind DROP DEFAULT');
        $this->addSql("ALTER TABLE parcours_point ADD media JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE parcours_point ALTER media DROP DEFAULT');
        $this->addSql("ALTER TABLE parcours_point ADD links JSON DEFAULT '[]' NOT NULL");

        $this->addSql(<<<'SQL'
            UPDATE parcours_point
            SET links = json_build_array(json_build_object('label', 'Site web', 'url', website_url))
            WHERE website_url IS NOT NULL AND website_url != ''
            SQL);

        $this->addSql('ALTER TABLE parcours_point ALTER links DROP DEFAULT');
        $this->addSql('ALTER TABLE parcours_point DROP website_url');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours_point ADD website_url VARCHAR(255) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE parcours_point
            SET website_url = links->0->>'url'
            WHERE jsonb_array_length(links::jsonb) > 0
            SQL);
        $this->addSql('ALTER TABLE parcours_point DROP kind');
        $this->addSql('ALTER TABLE parcours_point DROP media');
        $this->addSql('ALTER TABLE parcours_point DROP links');

        $this->addSql('ALTER TABLE parcours DROP depart_description');
        $this->addSql('ALTER TABLE parcours DROP depart_latitude');
        $this->addSql('ALTER TABLE parcours DROP depart_longitude');
    }
}
