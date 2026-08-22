.mode csv
.import ../data/amendment.csv amendment
.import ../data/lp.csv lp
.import ../data/name_history.csv name_history
.import ../data/reserved_name.csv reserved_name
.import ../data/tables.csv tables
.import ../data/corp.csv corp
.import ../data/llc.csv llc
.import ../data/merger.csv merger
.import ../data/officer.csv officer
CREATE INDEX corpIncorpDate ON corp (IncorpDate);
CREATE INDEX corpName ON corp (Name);
CREATE INDEX llcName ON llc (Name);
CREATE INDEX lpName ON lp (Name);
CREATE INDEX officerEntityId ON officer (EntityID);

-- Every business page is a lookup by EntityID. Without these, each one is a full
-- table scan of up to 1.5 million rows.
CREATE INDEX corpEntityId ON corp (EntityID);
CREATE INDEX llcEntityId ON llc (EntityID);
CREATE INDEX lpEntityId ON lp (EntityID);
