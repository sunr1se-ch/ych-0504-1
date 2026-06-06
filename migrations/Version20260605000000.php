<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rack, flush_slot, booking, probe_reading, and waitlist tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE rack_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE flush_slot_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE booking_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE probe_reading_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE waitlist_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE rack (
            id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            volume_m3 DOUBLE PRECISION NOT NULL,
            baseline_co2_ppm INT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX rack_name_idx ON rack (name)');

        $this->addSql('CREATE TABLE flush_slot (
            id INT NOT NULL,
            rack_id INT NOT NULL,
            start_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            duration_minutes INT NOT NULL,
            is_open BOOLEAN DEFAULT true NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX flush_slot_rack_start_idx ON flush_slot (rack_id, start_time)');
        $this->addSql('ALTER TABLE flush_slot ADD CONSTRAINT FK_FLUSH_SLOT_RACK FOREIGN KEY (rack_id) REFERENCES rack (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE booking (
            id INT NOT NULL,
            slot_id INT NOT NULL,
            status VARCHAR(20) DEFAULT \'pending\' NOT NULL,
            user_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            valve_opened_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            target_co2_ppm DOUBLE PRECISION DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EOE6F7534E71C4D2 ON booking (slot_id)');
        $this->addSql('CREATE INDEX booking_slot_status_idx ON booking (slot_id, status)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_BOOKING_SLOT FOREIGN KEY (slot_id) REFERENCES flush_slot (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE probe_reading (
            id INT NOT NULL,
            booking_id INT NOT NULL,
            co2_ppm INT NOT NULL,
            read_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX probe_reading_booking_read_idx ON probe_reading (booking_id, read_at)');
        $this->addSql('ALTER TABLE probe_reading ADD CONSTRAINT FK_PROBE_READING_BOOKING FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE waitlist (
            id INT NOT NULL,
            slot_id INT NOT NULL,
            user_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            priority INT DEFAULT 0 NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX waitlist_slot_priority_idx ON waitlist (slot_id, priority)');
        $this->addSql('ALTER TABLE waitlist ADD CONSTRAINT FK_WAITLIST_SLOT FOREIGN KEY (slot_id) REFERENCES flush_slot (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN rack.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN flush_slot.start_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN booking.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN booking.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN booking.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN booking.valve_opened_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN probe_reading.read_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE waitlist DROP CONSTRAINT FK_WAITLIST_SLOT');
        $this->addSql('ALTER TABLE probe_reading DROP CONSTRAINT FK_PROBE_READING_BOOKING');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_BOOKING_SLOT');
        $this->addSql('ALTER TABLE flush_slot DROP CONSTRAINT FK_FLUSH_SLOT_RACK');

        $this->addSql('DROP SEQUENCE rack_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE flush_slot_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE booking_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE probe_reading_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE waitlist_id_seq CASCADE');

        $this->addSql('DROP TABLE rack');
        $this->addSql('DROP TABLE flush_slot');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE probe_reading');
        $this->addSql('DROP TABLE waitlist');
    }
}
