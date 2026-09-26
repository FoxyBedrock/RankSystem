-- #!sqlite

-- #{ table
	-- #{ users
		CREATE TABLE IF NOT EXISTS RankSystemUsers
		(
			uuid           VARCHAR(36) PRIMARY KEY NOT NULL,
			name           VARCHAR(32) NOT NULL,
			ranks          TEXT        DEFAULT "",
			permissions    TEXT        DEFAULT ""
		);
	-- #}
	-- #{ nameIndex
		CREATE INDEX IF NOT EXISTS RankSystemUsers_name ON RankSystemUsers(name);
	-- #}
-- #}

-- #{ data
	-- #{ users
		-- #{ add
			-- # :uuid string
			-- # :name string
			-- # :ranks string ""
			-- # :permissions string ""
			INSERT OR IGNORE INTO
			RankSystemUsers(uuid, name, ranks, permissions)
			VALUES (:uuid, :name, :ranks, :permissions);
		-- #}
		-- #{ get
			-- # :uuid string
			SELECT * FROM RankSystemUsers WHERE uuid = :uuid;
		-- #}
		-- #{ getByName
			-- # :name string
			SELECT * FROM RankSystemUsers WHERE name = :name LIMIT 1;
		-- #}
		-- #{ set
			-- # :uuid string
			-- # :name string
			-- # :ranks string ""
			-- # :permissions string ""
			INSERT OR REPLACE INTO
			RankSystemUsers(uuid, name, ranks, permissions)
			VALUES (:uuid, :name, :ranks, :permissions);
		-- #}
		-- #{ getAll
			SELECT * FROM RankSystemUsers;
		-- #}
		-- #{ claim
			-- # :uuid string
			-- # :name string
			UPDATE RankSystemUsers SET uuid = :uuid WHERE name = :name AND uuid <> :uuid;
		-- #}
		-- #{ updateName
			-- # :uuid string
			-- # :name string
			UPDATE RankSystemUsers SET name = :name WHERE uuid = :uuid;
		-- #}
		-- #{ setRanks
			-- # :uuid string
			-- # :name string
			-- # :ranks string
			INSERT INTO RankSystemUsers(uuid, name, ranks)
			VALUES(:uuid, :name, :ranks)
			ON CONFLICT(uuid) DO UPDATE SET name = :name, ranks = :ranks;
		-- #}
		-- #{ setPermissions
			-- # :uuid string
			-- # :name string
			-- # :permissions string
			INSERT INTO RankSystemUsers(uuid, name, permissions)
			VALUES(:uuid, :name, :permissions)
			ON CONFLICT(uuid) DO UPDATE SET name = :name, permissions = :permissions;
		-- #}
		-- #{ delete
			-- # :uuid string
			DELETE FROM RankSystemUsers WHERE uuid = :uuid;
		-- #}
	-- #}
-- #}
