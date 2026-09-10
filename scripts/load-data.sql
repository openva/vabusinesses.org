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

-- Coordinates for each business, so that the businesses at one address can be
-- found by querying for a matching pair.
--
-- These are denormalised from data/addresses.db rather than joined against it at
-- request time. That database is keyed by a hash of the address text, and the
-- SCC's address text is not normalised: "814 E Main St", "814 East Main Street"
-- and "814 E. MAIN ST." are one building in Richmond but three different hashes.
-- They geocode to a single coordinate pair, so the coordinate is the only
-- reliable way to tell that those records share an address.
--
-- Left NULL here and filled in by scripts/geocode-businesses.php, which is the
-- only thing that reads data/addresses.db.
ALTER TABLE corp ADD COLUMN Latitude REAL;
ALTER TABLE corp ADD COLUMN Longitude REAL;
ALTER TABLE llc ADD COLUMN Latitude REAL;
ALTER TABLE llc ADD COLUMN Longitude REAL;
ALTER TABLE lp ADD COLUMN Latitude REAL;
ALTER TABLE lp ADD COLUMN Longitude REAL;
ALTER TABLE gp ADD COLUMN Latitude REAL;
ALTER TABLE gp ADD COLUMN Longitude REAL;
ALTER TABLE bt ADD COLUMN Latitude REAL;
ALTER TABLE bt ADD COLUMN Longitude REAL;
ALTER TABLE psa ADD COLUMN Latitude REAL;
ALTER TABLE psa ADD COLUMN Longitude REAL;

CREATE INDEX corpLatLng ON corp (Latitude, Longitude);
CREATE INDEX llcLatLng ON llc (Latitude, Longitude);
CREATE INDEX lpLatLng ON lp (Latitude, Longitude);
CREATE INDEX gpLatLng ON gp (Latitude, Longitude);
CREATE INDEX btLatLng ON bt (Latitude, Longitude);
CREATE INDEX psaLatLng ON psa (Latitude, Longitude);
