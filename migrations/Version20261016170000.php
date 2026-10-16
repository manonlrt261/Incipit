<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261016170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Objectifs de lecture par utilisateur et par année'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE objectif_annuel (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, annee INT NOT NULL, nombre_livres INT NOT NULL, UNIQUE INDEX objectif_utilisateur_annee (utilisateur_id, annee), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE objectif_annuel'); }
}
