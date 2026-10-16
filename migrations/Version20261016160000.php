<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261016160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Visibilité publique des souhaits, privée par défaut'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE souhait ADD publique TINYINT(1) DEFAULT 0 NOT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE souhait DROP publique'); }
}
