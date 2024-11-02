-- #!sqlite
-- #{ worldactionlog
-- #  { init
-- #    { actions
CREATE TABLE IF NOT EXISTS actions(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    world VARCHAR(255) NOT NULL,
    x INTEGER NOT NULL,
    y INTEGER NOT NULL,
    z INTEGER NOT NULL,
    action VARCHAR(255) NOT NULL,
    timestamp INTEGER NOT NULL
);
-- #    }
-- #    { actions_idx_world
CREATE INDEX IF NOT EXISTS idx_world ON actions(world);
-- #    }
-- #    { actions_idx_coords
CREATE INDEX IF NOT EXISTS idx_coords ON actions(x, y, z);
-- #    }
-- #    { actions_tags
CREATE TABLE IF NOT EXISTS actions_tags(
    id INTEGER NOT NULL,
    tag VARCHAR(255) NOT NULL,
    value BLOB NOT NULL,
    PRIMARY KEY(id, tag)
);
-- #    }
-- #  }
-- #  { insert
-- #    { entry
-- #      :world string
-- #      :x int
-- #      :y int
-- #      :z int
-- #      :action string
-- #      :timestamp int
INSERT INTO actions(world, x, y, z, action, timestamp) VALUES(:world, :x, :y, :z, :action, :timestamp);
-- #    }
-- #    { tag
-- #      :id int
-- #      :tag string
-- #      :value string
INSERT OR REPLACE INTO actions_tags(id, tag, value) VALUES(:id, :tag, :value);
-- #    }
-- #  }
-- #  { select
-- #    { latest_header_count
-- #      :world string
-- #      :x int
-- #      :y int
-- #      :z int
-- #      :radius float
-- #      :action string
SELECT COUNT(*) AS c FROM actions
WHERE   world=:world
        AND SQRT(POW(x - :x, 2) + POW(y - :y, 2) + POW(z - :z, 2)) <= :radius
        AND action LIKE "%" || :action || "%";
-- #    }
-- #    { latest_headers
-- #      :world string
-- #      :x int
-- #      :y int
-- #      :z int
-- #      :radius float
-- #      :offset int
-- #      :length int
-- #      :action string
SELECT id, x, y, z, action, timestamp FROM actions
WHERE   world=:world
        AND SQRT(POW(x - :x, 2) + POW(y - :y, 2) + POW(z - :z, 2)) <= :radius
        AND action LIKE "%" || :action || "%"
ORDER BY id DESC LIMIT :offset, :length;
-- #    }
-- #    { tags_by_id
-- #      :id int
SELECT tag, value FROM actions_tags WHERE id=:id;
-- #    }
-- #  }
-- #}