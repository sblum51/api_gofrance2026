<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728100652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Parcours.slug (généré depuis le nom), unique par organisation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours ADD slug VARCHAR(255) DEFAULT NULL');

        $this->backfillSlugs();

        $this->addSql('ALTER TABLE parcours ALTER slug SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX parcours_organization_slug_unique ON parcours (organization_id, slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX parcours_organization_slug_unique');
        $this->addSql('ALTER TABLE parcours DROP slug');
    }

    private function backfillSlugs(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, name, organization_id FROM parcours');
        $usedSlugsByOrganization = [];

        foreach ($rows as $row) {
            $organizationId = (string) $row['organization_id'];
            $base = $this->slugify((string) $row['name']);
            $slug = $base;
            $suffix = 2;

            while (in_array($slug, $usedSlugsByOrganization[$organizationId] ?? [], true)) {
                $slug = sprintf('%s-%d', $base, $suffix);
                ++$suffix;
            }

            $usedSlugsByOrganization[$organizationId][] = $slug;

            $this->addSql('UPDATE parcours SET slug = :slug WHERE id = :id', [
                'slug' => $slug,
                'id' => $row['id'],
            ]);
        }
    }

    private function slugify(string $name): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $transliterated));
        $slug = trim($slug, '-');

        return '' !== $slug ? $slug : 'parcours';
    }
}
