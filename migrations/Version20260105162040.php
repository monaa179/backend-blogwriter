<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105162040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article CHANGE suggested_title suggested_title VARCHAR(255) DEFAULT NULL, CHANGE suggested_description suggested_description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE article_version DROP FOREIGN KEY `FK_52CE97747294869C`');
        $this->addSql('ALTER TABLE article_version ADD CONSTRAINT FK_52CE97747294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article CHANGE suggested_title suggested_title VARCHAR(255) NOT NULL, CHANGE suggested_description suggested_description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE article_version DROP FOREIGN KEY FK_52CE97747294869C');
        $this->addSql('ALTER TABLE article_version ADD CONSTRAINT `FK_52CE97747294869C` FOREIGN KEY (article_id) REFERENCES article (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
