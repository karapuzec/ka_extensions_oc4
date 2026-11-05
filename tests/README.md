# Ka Extensions install tests (OpenCart 4)

Basic integration tests: build the package, install it on a clean OpenCart store through the admin
area over HTTP (as readme.ka_extensions.txt describes), check the menu, pages and logs, uninstall.
The `tests/` folder is not a part of the package: the package is built from `extension/ka_extensions`
only, like `pack_ka_extensions.bat` does (`mod1`, `mod2` and other test extensions are not included).

## Requirements

- Linux or WSL, PHP 8.1+ CLI with `curl`, `mysqli` and `zip` extensions, `unzip`
- MySQL/MariaDB user which can create and drop the test database
- OpenCart 4.1.0.3 or newer release archive (or unpacked directory with `upload` inside)

## Running

```sh
export KA_TEST_DB_USER=ka_test KA_TEST_DB_PASSWORD=...
php tests/run.php --oc=opencart-4.1.0.4.zip
php tests/run.php --oc=opencart-4.1.0.3.zip --port=8097
```

Options: `--port` (php built-in server port, default 8096), `--work-dir` (default `/tmp/ka_ext_test_<port>`),
`--db` (default `kat_ext_<port>`, it is dropped and created again), `--keep` (skip uninstall and leave
the store running for manual checks), `--tests` (comma separated test methods to run after the store
setup, for example `--tests=testInstall,testModification,testSafeMode`).

## What is tested

- `testPackage` - the archive has the expected files at its root, no `active.kamod`, valid install.json and ocmod xml
- `testInstall` - upload and install through Extension Installer, files are copied, no errors in the log
- `testModification` - enable 'Ka Extensions Ocmod', refresh, framework.php is patched, all ocmod operations
  are applied, the Modification page loads without errors
- `testMenu` - 'Ka Extensions' item is in the Extensions menu, the Ka Extensions page opens
- `testLicenseServer` - the license requests of the Ka Extensions page with a local test server
  (`fixtures/license_server/router.php`, KA_STORE_URL in admin/config.php): checkModules with all modules and the
  edition oc4, a module of another edition, free / expired modules, the fallback to the old domain_info request on an error,
  an old server, register_key with api_version on a new and an old server
- `testSafeMode` - the safe mode code is saved on the Ka Extensions page (wrong characters are rejected), the
  `route=ka_safe_mode&code=` URL sets an httponly SameSite=Lax cookie and turns kamod off for this browser only,
  a wrong code is ignored, the code survives a kamod rebuild, a changed or empty code and the exit URL turn safe mode off
- `testStorefront` - storefront pages load without PHP errors
- `testUninstall` - uninstall through Extension Installer, files and modification are removed, the menu item is gone

## Building the package

```sh
php tests/build-package.php /path/to/ka_extensions.ocmod.zip
```
