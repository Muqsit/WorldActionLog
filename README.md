# WorldActionLog
This plugin logs a few player-related world actions to let administrators identify suspicious events.
In instances where inventories are looted by potential insiders or hackers with chest ESP, running
`/wal 50 block_entity` returns logs in a 50-block radius around you related to block_entity (place,
break, and interactions).


### Actions
The following log actions are available by default:

| Action                      | Description                                                           | Available Tags (used to format log messages)                                                     |
|-----------------------------|-----------------------------------------------------------------------|--------------------------------------------------------------------------------------------------|
| `wal:block_entity_break`    | Logs whenever a player breaks a block entity  (e.g., chest or barrel) | `{world}` `{x}` `{y}` `{z}` `{player_xuid}` `{player_uuid}` `{player_gamertag}` `{block_entity}` |
| `wal:block_entity_interact` | Logs whenever a player interacts with a block entity                  | `{world}` `{x}` `{y}` `{z}` `{player_xuid}` `{player_uuid}` `{player_gamertag}` `{block_entity}` |
| `wal:block_entity_place`    | Logs whenever a player places a block entity                          | `{world}` `{x}` `{y}` `{z}` `{player_xuid}` `{player_uuid}` `{player_gamertag}` `{block_entity}` |
| `wal:chunk_enter`           | Logs when a player enters a new chunk	                                | `{world}` `{x}` `{y}` `{z}` `{player_xuid}` `{player_uuid}` `{player_gamertag}`                  |
| `wal:chunk_exit`            | Logs when a player exits a chunk                                      | `{world}` `{x}` `{y}` `{z}` `{player_xuid}` `{player_uuid}` `{player_gamertag}`                  |
| `wal:inventory_open`        | Logs when a player opens an inventory (e.g., chest or barrel)         | `{world}` `{x}` `{y}` `{z}` `{player_xuid}` `{player_uuid}` `{player_gamertag}` `{block_entity}` |


### Commands
`/wal` command requires `worldactionlog.command.wal` permission (default: op-only).

| Command                                               | Player                             |
|-------------------------------------------------------|------------------------------------|
| `/wal <radius> [action] [page=1]`                     | View logs around you (player only) |
| `/wal <world> <x> <y> <z> <radius> [action] [page=1]` | View logs around a specific point  |

```
> wal world 100 100 100 400 inv
WorldActionLog Logs (1 / 1)
1. (#459) 2024-11-02 10:20:28 wal:inventory_open NeedleGalaxy opened chest at 59x 225y 53z
2. (#378) 2024-11-02 08:55:47 wal:inventory_open NeedleGalaxy opened chest at 396x 68y 139z
3. (#367) 2024-11-02 08:55:35 wal:inventory_open NeedleGalaxy opened chest at 396x 68y 139z
```