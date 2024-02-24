<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240224112437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add emojis field to {entry,post}{,_comment}';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entry ADD emojis JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE entry_comment ADD emojis JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD emojis JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE post_comment ADD emojis JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entry DROP emojis');
        $this->addSql('ALTER TABLE entry_comment DROP emojis');
        $this->addSql('ALTER TABLE post DROP emojis');
        $this->addSql('ALTER TABLE post_comment DROP emojis');
    }
}
