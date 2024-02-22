<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240222071845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add tables for Emoji and EmojiIcon';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE emoji_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE emoji_icon_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE emoji (id INT NOT NULL, icon_id INT NOT NULL, shortcode TEXT NOT NULL, category TEXT DEFAULT NULL, icon_url TEXT DEFAULT NULL, ap_id TEXT DEFAULT NULL, ap_domain TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B64BF63254B9D732 ON emoji (icon_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_domain_shortcode_idx ON emoji (ap_domain, shortcode)');
        $this->addSql('CREATE INDEX IDX_B64BF63264C19C1 ON emoji (category);');
        $this->addSql('CREATE INDEX IDX_B64BF632C2B7362F ON emoji (ap_domain)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B64BF632904F155E ON emoji (ap_id)');
        $this->addSql('CREATE TABLE emoji_icon (id INT NOT NULL, file_name TEXT NOT NULL, file_path TEXT NOT NULL, sha256 BYTEA NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX emoji_icon_file_name_idx ON emoji_icon (file_name)');
        $this->addSql('CREATE UNIQUE INDEX emoji_icon_sha256_idx ON emoji_icon (sha256)');
        $this->addSql('ALTER TABLE emoji ADD CONSTRAINT FK_B64BF63254B9D732 FOREIGN KEY (icon_id) REFERENCES emoji_icon (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE emoji_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE emoji_icon_id_seq CASCADE');
        $this->addSql('ALTER TABLE emoji DROP CONSTRAINT FK_B64BF63254B9D732');
        $this->addSql('DROP TABLE emoji');
        $this->addSql('DROP TABLE emoji_icon');
    }
}
