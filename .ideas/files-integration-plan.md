<!--
  - SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Files integration — plan

Expose a JMAP account's file store (`urn:ietf:params:jmap:filenode`) as a
folder in the user's Nextcloud Files, mounted at a location the user picks.

Requirements:

- **a)** The user chooses where the folder is mounted.
- **b)** Two modes, `live` and `cached`, stored per mount. Build `live`
  first.

## What "live" and "cached" mean for Files

Unlike contacts and calendars, Files always writes metadata to `oc_filecache`.
The Files UI, search, sharing and previews all read from it, so a mounted
storage cannot skip it. The two modes therefore differ in how that cache is
kept current and where the file contents live:

| | live (v1) | cached (later) |
|---|---|---|
| Listings | Fetched from JMAP when a folder is opened (`hasUpdated` → rescan) | Kept up to date by a background job using `FileNode/changes` |
| Contents | Streamed from the JMAP blob download URL on every read | Local copy, refreshed when the blob changes |
| Writes | Upload the blob, then `FileNode/set` | Written locally, pushed by the harmonization job |
| Offline remote | Mount reports unavailable | Readable from the local copy |

## Verified building blocks

**JMAP client** (`vendor/sebastiankrupinski/jmap-client-php`) already has:

- `Requests/Files/Node{Get,Query,Set,Changes,QueryChanges,Filter}` using
  `urn:ietf:params:jmap:filenode`.
- Node fields: `id`, `parentId`, `name`, `blobId` (null for folders), `size`,
  `type`, `role`, `created`, `modified`, `myRights` (read/write/share).
- `NodeSet::destroyChildren()` and `onExists()`.
- `Client::downloadStream()` (PSR-7 stream) and `Client::upload()` (accepts a
  stream resource).

**Core** (checked against current master):

- `IMountProvider::getMountsForUser()`, registered through
  `IMountProviderCollection::registerProvider()` in `boot()`.
- `IPartialMountProvider::getMountsForPath()` (NC 33+). Optional.
- `SetupManager` loads apps of type `filesystem` before it sets up mounts,
  so `info.xml` must declare `<filesystem/>`.
- `IMovableMount` (`moveMount`/`removeMount`) lets the user move, rename or
  unmount the folder from the Files UI. `files_external`'s `PersonalMount` is
  the reference implementation.
- `OC\Files\Storage\Common` is the base class for remote storages.
  `OC\Files\Storage\DAV` is the closest reference.
- Mount options read by core: `encrypt`, `previews`, `enable_sharing`,
  `readonly`.
- `files_trashbin` sends a `MoveToTrashEvent` before it moves a file to the
  trash. A listener can block this for our storage.

**App:** nothing stores files mounts yet. All tables are created by the one
migration, `Version1000Date20250101`, and every `create*Table()` method in it
first checks `hasTable()`.

## Design

### Data model

A mount is a **files collection**. It follows the same idea as the contacts,
events and tasks collections: a per-user, per-service mapping from a remote
id to local settings. It keeps files' own table and store, though, rather
than sharing `jmapc_collections` and `BaseStore`.

**Table:** `jmapc_collections_file`, created by `createFilesCollectionsTable()`
in the existing `Version1000Date20250101`.

| Column | Type | Null | Purpose |
|---|---|---|---|
| `id` | BIGINT, autoincrement | no | Primary key; storage id `jmapc::files::{id}` |
| `uid` | STRING(255) | no | Owner user id |
| `sid` | INTEGER | no | Service id |
| `ccid` | STRING(255) | yes | Remote root node id; `null` means the top level (v1 always stores `null`) |
| `uuid` | STRING(255) | no | Generated, as for other collections |
| `label` | STRING(255) | yes | Display name; defaults to the service label |
| `location` | STRING(4000) | no | Mount path relative to the user's files root (4000 matches `oc_mounts`) |
| `mode` | STRING(8) | no | `live` (later `cached`) |
| `visible` | BOOLEAN | yes | Enabled or disabled |

- **Indexes:** `uid` and `sid`.
- **No unique index on `(uid, location)`:** a 4000-character column is too
  long for a MySQL index key, so `FilesService` checks for duplicates instead.
- **Not in v1:** `jmapc_services` gets no `files_mode`, because the mode lives
  on the collection. There is no files entity table and no chronicle.
- **Later:** cached mode adds its state and lock columns (`hisn`, `hesn`,
  `hlock*`) and a node table.

**Changes and history.** `oc_filecache` already records what the files are,
so files needs no chronicle. Cached mode may still need a small queue of local
changes waiting to be uploaded, because `oc_filecache` doesn't track that.

**Code:**

- **`Store/Local/FileCollectionEntity`:** a standalone `Entity` with typed
  `sid` and `visible`.
- **`Store/Local/Filters/FileCollectionFilter`.**
- **`Store/Local/FilesStore`:** extends `QBMapper` (typed binding), not
  `BaseStore`. Its methods are named like the other modules' store methods:
  - `collectionList`, `collectionFetch`, `collectionCreate`,
    `collectionModify`, `collectionDelete`;
  - `collectionDeleteByService`, `collectionDeleteByUser`;
  - `collectionListActive(uid, mode)`, which joins `jmapc_services` for
    enabled, connected services.
- **`Service/FilesService`:** the layer that the controller, the mount
  provider and occ use. It checks, in this order:
  1. the caller owns the service;
  2. the service doesn't use OAuth;
  3. the service has no collection yet;
  4. the mode is supported;
  5. the location is valid;
  6. the server supports files (`filenode`).

**Updating the existing migration.** Nextcloud records a migration as run and
never runs it again. Because every `create*Table()` method checks
`hasTable()` first, re-running it only creates what is missing:
`occ migrations:execute integration_jmapc 1000Date20250101`. This only works
while the app is unreleased; after the first release, schema changes need a
new migration class.

**Rules (v1):**

- One files collection per service.
- Deleting a collection, disconnecting a service, or deleting a user removes
  the collection rows and their storage's cached file details.

### Mount provider

`lib/Providers/Files/MountProvider.php` implements `IMountProvider`:

- Query only the database, in one query: files collections with `uid`,
  `visible` and `mode = 'live'`, joined to services that are enabled and
  connected. **No network calls here**, because this runs on every filesystem
  setup.
- Return one `Live\MountPoint` per collection at `/{uid}/files/{location}`,
  using the storage class `Live\Storage` and the arguments
  `['cid' => …, 'sid' => …]`.
- Mount options: `encrypt => false` (the contents live remotely),
  `previews => true`, `enable_sharing => false`. Sharing is off in v1 because
  shares of remote content would break when the service is disconnected.
- Register it as a service in `register()` and call
  `IMountProviderCollection::registerProvider()` in `boot()`.

`lib/Providers/Files/Live/MountPoint.php` extends `OC\Files\Mount\MountPoint`
and implements `IMovableMount`:

- `moveMount($target)` strips the `/{uid}/files` prefix and saves the result
  as the mount's `location`. This means drag and rename in Files also changes the
  mount location (requirement a).
- `removeMount()` deletes the mount row. It must **never** delete remote
  data.

### Live storage

`lib/Providers/Files/Live/Storage.php` extends `OC\Files\Storage\Common`.

- **Path resolution.** FileNode is ID-based, while the storage API is
  path-based. A `NodeResolver` maps path → node, using a per-request array
  plus a short-TTL distributed `ICache` keyed by service and path.
- **Root.** Servers (Stalwart, Cyrus) have no root node. The top level is the
  set of nodes without a parent. The storage root `''` therefore maps to
  `parentId = null`, listed with `NodeFilter::in(null)`. New top-level nodes
  are created with `parentId = null`. The root has no node of its own, so its
  `stat` is synthesized:
  - Type: folder.
  - Permissions: full, unless the account is read-only.
  - mtime: the newest child's `modified`.
  - ETag: derived from the `FileNode` state string.
- **Reads:** `opendir` (`FileNode/query` with `in(parentId)` plus `get`),
  `stat`, `getMetaData`, `filetype` (no `blobId` means a folder),
  `file_exists`, and `getDirectoryContent`. Override `getDirectoryContent` so a
  folder takes one round trip instead of N `stat` calls.
- **`fopen('r')`** wraps `downloadStream()` in a PHP stream wrapper.
- **`fopen('w'/'a')`** writes to a temp file. On close it calls `upload()`,
  then `FileNode/set` to create the node, or to update its `blobId`.
  Implement `writeStream()` so uploads stream without going through a temp
  file.
- **`mkdir`, `unlink`, `rmdir`** use `FileNode/set` (`destroyChildren(true)`
  for `rmdir`).
- **`rename`** updates `parentId` and `name`.
- **`copy`** creates a new node with the same `blobId`, which copies on the
  server with no data transfer.
- **`touch`** updates `modified`.
- **`getPermissions`** maps `myRights` to `Constants::PERMISSION_*`.
- **`getETag`** is derived from `blobId` and `modified`.
- **`hasUpdated`** compares `modified`. For folders, also compare the stored
  `FileNode` state string, so changes to children trigger a rescan.
- **`free_space`** uses the Quota capability if the server has it, and
  returns `SPACE_UNKNOWN` otherwise.
- **`getId()`** returns `jmapc::files::{cid}`. It must stay stable so
  `oc_filecache` rows survive between requests. Moving the mount keeps the
  same id.
- **Errors:** if the remote is unreachable or authentication fails, throw
  `StorageNotAvailableException`. **Never return an empty listing on
  failure.** The scanner would treat it as "all files deleted" and remove the
  cache rows, along with any shares and tags on them.
- **Client:** build it lazily on first use with `RemoteService::freshClient()`.
  Never build it in the constructor.

### Lifecycle hooks

- **Removing a mount, disconnecting a service, or deleting a user** (the
  mount endpoint, the existing `Disconnect` path, `UserDeletedListener`):
  delete the mount rows, then remove each storage's filecache entries.
  `OC\Files\Cache\Storage::remove()` no longer exists. Instead, look up the
  numeric id with `Storage::getStorageById('jmapc::files::{cid}')`, call
  `Storage::removeFileCacheEntries()`, then delete the `oc_storages` row.
  This lands with the storage in PR 2, because PR 1 creates no storages.
- **Trash:** add a `MoveToTrashEvent` listener that turns off the trash for
  nodes on our storage. Otherwise every delete downloads the file into the
  local trash. The remote already applies its own deletion semantics.

### Settings: choosing the mount location (requirement a)

**Backend.** Add mount routes to `UserConfigurationController`:

- `GET /files/collections/list`
- `POST /files/collections/create`, with `sid`, `location`, `mode` and `label`
- `POST /files/collections/modify`, with `id`, `location`, `mode`, `label` and
  `visible`
- `POST /files/collections/delete`, with `id`

Each route does these checks:

- Check that the caller owns the service (`fetchByUserIdAndServiceId`), and
  for an existing mount, that `mount.uid` is the caller. Do this **before**
  anything else.
- Reject a second mount for the same service (v1 rule).
- Normalize the path with `Filesystem::normalizePath`. Reject `/`, `..`,
  empty segments and blacklisted names (`IFilenameValidator`).
- Reject a path where a real node already exists in the user folder, because
  a mount would hide it. Also reject a path inside another mount (shares,
  groupfolders, another JMAP mount), using `IRootFolder` /
  `getUserFolder($uid)` and the node's mount.
- Reject the request if the session lacks `urn:ietf:params:jmap:filenode`.
  Record this capability at connect time so the UI can hide the option.
- Reject services that use OAuth (`auth = 'OA'`) until OAuth is implemented.

**Frontend** (`SettingsConnectedService.vue`) gets a new "Files" section:

- A switch to turn Files on (creates the mount with `mode = 'live'`, and
  deletes it when switched off). Add a mode selector once `cached` exists.
- A parent folder chosen with the `@nextcloud/dialogs` `getFilePickerBuilder`
  (folders only).
- A folder name field, defaulting to the service label.
- Show the resolved path and any validation error from the endpoint.

**occ.** Extend `jmapc:connect`/`jmapc:show`, or add `jmapc:files`, so the
mount can be configured and tested before the UI exists.

## Delivery: split into PRs

1. **Schema and settings endpoints:** the `jmapc_collections_file` table in
   the existing migration, `FileCollectionEntity`/`FilesStore`/`FilesService`,
   the `/files/collections/*` routes with validation, cleanup when a service
   or user is deleted, the `jmapc:files:*` occ commands, and unit tests.
2. **Read-only live mount:** `<filesystem/>`, the mount provider, the mount
   point, and a read-only storage (browse, download, metadata, permissions,
   error handling). Tests use a mocked JMAP client.
3. **Writes:** upload, mkdir, delete, rename, copy and touch, plus the trash
   listener and filecache cleanup on disconnect.
4. **Settings UI:** the Files section with the folder picker.
5. **Later:** `IPartialMountProvider`, then the `cached` mode (a
   `FileNode/changes`-driven job and a local blob store).

## Tests

- `NodeResolver`: path walking, cache hits and invalidation after
  rename and delete.
- `Live\Storage` with a mocked client, for each operation. Include the error
  cases (remote down → `StorageNotAvailableException`, never an empty
  listing).
- `FilesStore`: CRUD, `collectionDeleteByService`, `collectionDeleteByUser`, `collectionListActive`.
- `MountProvider`: the filter and join conditions, and the mount path built
  from `location`.
- `MountPoint::moveMount` / `removeMount`: these persist the change and
  never touch remote data.
- Controller: ownership check, path validation, and conflict rejection.
- Manual, on a live instance against a FileNode server (Stalwart and Cyrus
  implement it):
  - The mount appears at the chosen path.
  - Move and rename in the Files UI change the mount location.
  - Upload and download of large files streams without memory blow-up.
  - Behaviour when the remote is offline.

## Findings from PR 2 (live server)

- **Top-level listing:** this needs `FileNode/query` with the filter
  `isTopLevel: true`. The client's `NodeFilter::in(null)` sends
  `isTopLevel: null`, and the server answers `parentId: null` with HTTP 400.
- **`myRights` keys:** `mayRead`, `mayAddChildren`, `mayRename`,
  `mayDelete`, `mayModifyContent`, `mayShare`. The client's
  `NodePermissions::write()` reads `mayWrite`, which the server doesn't send,
  so the app reads `myRights` directly.
- **Nodes:** they have `nodeType` (`directory`, `file`), `changed` and
  `accessed`. The download URL template contains `{name}` and
  `accept={type}`.
- **Read-only:** v1 sets the `readonly` mount option. Core then wraps the
  storage in a read-only permission mask, so the storage can already report
  the real rights. PR 3 removes the option.
- **Freshness:** the mount sets `filesystem_check_changes` to
  `Watcher::CHECK_ONCE`, and `hasUpdated()` always reports folders as changed.
  Opening a folder therefore re-reads its listing once per request.
- **Folder ETags:** a folder ETag includes the account's `FileNode` state,
  because folders carry no change marker for their children. ETags stay
  stable while nothing changes. After any change in the account, they change
  for every folder that gets re-scanned.
- **Updating an install:** core caches the `<types>` from `info.xml`. Existing
  installs only pick up `<filesystem/>` after a version bump, or after
  `occ config:app:set integration_jmapc types --value="filesystem,dav"`.

## Decisions

- **Sharing:** off in v1 (`enable_sharing => false`).
- **Root:** no root node. The top level is the set of nodes without a parent
  (see "Live storage").
- **Accounts:** one mount per service, each with its own location. A service
  mounts its primary `filenode` account.
- **Storage of mounts:** a files collection in its own table,
  `jmapc_collections_file`, with `mode` per collection. It uses a standalone
  `FilesStore` rather than `BaseStore`, and has no chronicle.
- **Authentication:** OAuth is not implemented yet, so v1 works with the
  basic and JSON-basic auth types only. When OAuth support is added, token
  refresh must be handled in `freshClient()` before OAuth services can be
  mounted.
