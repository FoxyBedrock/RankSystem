-- #!mysql

-- #{ table
	-- #{ users
		CREATE TABLE IF NOT EXISTS RankSystemUsers
		(
			uuid           CHAR(36)    PRIMARY KEY NOT NULL,
			name           VARCHAR(32) NOT NULL,
			ranks          TEXT        DEFAULT NULL,
			permissions    TEXT        DEFAULT NULL,
			KEY RankSystemUsers_name (name)
		);
	-- #}
-- #}

-- #{ data
	-- #{ users
		-- #{ add
			-- # :uuid string
			-- # :name string
			-- # :ranks string ""
			-- # :permissions string ""
			INSERT IGNORE INTO
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
			INSERT INTO
			RankSystemUsers(uuid, name, ranks, permissions)
			VALUES (:uuid, :name, :ranks, :permissions)
			ON DUPLICATE KEY UPDATE
				name = :name,
				ranks = :ranks,
				permissions = :permissions;
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
				ON DUPLICATE KEY UPDATE
					name = :name,
					ranks = :ranks;
		-- #}
		-- #{ setPermissions
			-- # :uuid string
			-- # :name string
			-- # :permissions string
			INSERT INTO RankSystemUsers(uuid, name, permissions)
				VALUES(:uuid, :name, :permissions)
				ON DUPLICATE KEY UPDATE
					name = :name,
					permissions = :permissions;
		-- #}
		-- #{ delete
			-- # :uuid string
			DELETE FROM RankSystemUsers WHERE uuid = :uuid;
		-- #}
	-- #}
-- #}
