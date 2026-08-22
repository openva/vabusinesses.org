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

-- Entity types the SCC added after this site was built: general partnerships,
-- business trusts and public service authorities. Same schema as lp.csv.
.import ../data/gp.csv gp
.import ../data/bt.csv bt
.import ../data/psa.csv psa
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
CREATE INDEX gpEntityId ON gp (EntityID);
CREATE INDEX btEntityId ON bt (EntityID);
CREATE INDEX psaEntityId ON psa (EntityID);
