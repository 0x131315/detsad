<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210612165143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE presence_history (id INT AUTO_INCREMENT NOT NULL, child_id INT NOT NULL, date DATE NOT NULL, presence TINYINT(1) DEFAULT NULL, INDEX IDX_3AE0F19EDD62C21B (child_id), UNIQUE INDEX UNIQ_3AE0F19EDD62C21BAA9E377A (child_id, date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE presence_history ADD CONSTRAINT FK_3AE0F19EDD62C21B FOREIGN KEY (child_id) REFERENCES child (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE presence_history');
    }
}
