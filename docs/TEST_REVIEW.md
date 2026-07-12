# Test Review Report — fast-php-log-viewer

## Coverage Summary

| Metric   | Covered | Total | Percentage |
|----------|---------|-------|------------|
| Classes  | 7       | 22    | **31.82%** |
| Methods  | 62      | 125   | **49.60%** |
| Lines    | 822     | 1538  | **53.45%** |

### Per-Class Coverage

| Class                        | Methods     | Lines       |
|------------------------------|-------------|-------------|
| ConfigManager                | 68.75% (11/16)  | 82.76% (72/87)   |
| **LogConfig**                | **5.88% (1/17)**  | **3.70% (6/162)**   |
| AppConfigController          | 100% (3/3)   | 100% (8/8)    |
| DirectoryController          | 50% (3/6)    | 76.19% (16/21)  |
| JsonResponseTrait            | 100% (1/1)   | 100% (2/2)    |
| LogController                | 16.67% (1/6) | 66.67% (58/87)  |
| SSHController                | 33.33% (2/6) | 57.14% (44/77)  |
| SetupController              | 50% (2/4)    | 74.07% (20/27)  |
| SetupMiddleware              | 100% (2/2)   | 100% (8/8)    |
| LegacyRouter                 | 100% (4/4)   | 100% (7/7)    |
| **DockerExecService**        | **33.33% (3/9)**  | **24.51% (25/102)** |
| ErrorContextTrait            | 0% (0/1)     | 75% (3/4)     |
| FileAccessValidator          | 100% (3/3)   | 100% (28/28)  |
| GlobLogFinder                | 100% (2/2)   | 100% (21/21)  |
| **LogParser**                | **50% (2/4)**     | **38.63% (90/233)** |
| LogScanner                   | 25% (1/4)    | 69.09% (38/55)  |
| PathResolver                 | 100% (3/3)   | 100% (32/32)  |
| RemoteLogFinder              | 66.67% (2/3) | 83.87% (52/62)  |
| **SSH**                      | **33.33% (5/15)** | **44.95% (49/109)** |
| SecurityService              | 50% (2/4)    | 93.02% (40/43)  |
| SetupWizard                  | 81.82% (9/11) | 98.54% (203/206) |

**Not covered at all (0% — no test file):**
- `AppBootstrap` (bootstrap class, low priority)
- `Bootstrap\app.php`, `container.php`, `routes.php`, `frontend.php` (bootstrap files, typically hard to unit-test)
- `LogFinderInterface` (interface, no code to test)

---

## Test Results

```
Tests: 187, Assertions: 4970
Failures: 1, Skipped: 3
```

### Failing Test

| Test | File | Issue |
|------|------|-------|
| `LogControllerTest::testGetFilesWithRealGlobLogFinderAndRelativePath` | tests/Controller/LogControllerTest.php:294 | Asserts `logs/` directory is non-empty, but the directory has no log files — environment-dependent |

**Root cause:** The test uses a real `GlobLogFinder` to scan the project's `logs/` directory, expecting it to contain `.log` files. The `logs/` directory is empty (no log files committed). This test will always fail in a clean checkout or CI environment.

**Fix:** Either seed the `logs/` directory with a test fixture file, or use a temp directory like the test at line 236 does.

### Skipped Tests (3)

| Test | Reason |
|------|--------|
| `SSHTest::testConnectThrowsExceptionWhenPasswordMissingForPasswordAuth` | SSH2 extension not available |
| `SSHTest::testDisconnectClosesConnection` | SSH2 extension not available |
| `SSHControllerTest::testListFilesWithRealFrogConnection` (+ 2 more) | Hard-coded `markTestSkipped('Serwer frog01 niedostępny')` |

---

## Test Correctness Issues

### 1. CRITICAL: Hardcoded credentials in test file
**File:** `tests/Controller/SSHControllerTest.php:242-268`
```php
'ssh_host' => 'frog01.mikr.us',
'ssh_user' => 'frog',
'ssh_port' => 10137,
'ssh_password' => 'GxCdTbACI7',
```
Real SSH credentials are committed to the repository. Even though the test is skipped, these credentials are visible in the code. Should be removed or moved to env vars.

### 2. MEDIUM: Environment-dependent test always fails
**File:** `tests/Controller/LogControllerTest.php:269-295`
`testGetFilesWithRealGlobLogFinderAndRelativePath` expects files in `logs/` but none exist. Should create test fixtures in a temp dir (like the neighboring `testGetFilesWithRealGlobLogFinderFindsLogsInTempDir` already does correctly).

### 3. MEDIUM: Hardcoded developer path in assertions
**File:** `tests/Service/SetupWizardTest.php:92`
```php
$this->assertStringContainsString('/home/mariusz/PhpstormProjects/fast-php-log-viewer/logs', ...);
```
Also in `tests/Service/SetupWizardPropertyTest.php:74`.
This asserts against the developer's local workstation path. Will fail on any other machine. The test currently passes because the warning message includes the current project root via `dirname(__DIR__, 2)`, but the assertion expects `/home/mariusz/PhpstormProjects/fast-php-log-viewer/logs` specifically.

**Wait — this passes in CI?** Actually, the setup wizard's `getSkipWarning()` likely uses a hardcoded path string in production code. If so, the test is correct but both test and production code contain a hardcoded developer path that should be dynamic.

### 4. LOW: Test name doesn't match what it tests
**File:** `tests/LogScannerTest.php:124-131`
`testScanCommonDirectoriesScansExistingDirs` actually calls `scanDirectory()` (not `scanCommonDirectories()`). The test name is misleading.

### 5. LOW: Redundant test method
**File:** `tests/Service/SetupWizardTest.php:334-342`
`testFinalizeMarksSetsupComplete` (note typo: "Setsup") is identical to `testProcessFinalizeMarksSetupComplete` on line 237 — tests the same thing with same assertions.

### 6. LOW: Weak assertion in SecurityTest::testMaxFileSize
**File:** `tests/SecurityTest.php:106-119`
This test doesn't test any method of `SecurityService`. It only calculates `10 * 1024 * 1024` and checks its value equals `10485760`, then verifies `strlen()` against that constant. It's testing PHP math, not `SecurityService` behavior. The test should call a method on SecurityService to verify file size enforcement.

### 7. LOW: Property tests use `forAll()` without generators
**File:** `tests/Config/ConfigManagerPropertyTest.php:49-61`
```php
$this->forAll()->then(function () use (&$generatedIds) { ... });
```
`forAll()` with no generators defaults to 1 iteration in Eris. These property tests run with minimal input diversity.

### 8. INFO: SetupWizardPropertyTest asserts trivially
**File:** `tests/Service/SetupWizardPropertyTest.php:156-214`
`testSSHDirectoriesFilteredWhenDisabled` simulates filtering logic locally instead of testing the actual application's filtering. Line 211: `$this->assertEquals(count($directories), count($directories))` always true.

---

## Untested Classes / Major Coverage Gaps

### Classes with NO dedicated test coverage:

| Class | Priority | Why |
|-------|----------|-----|
| `LogConfig` (5.88% methods) | **HIGH** | Core persistence layer — CRUD, backup/restore, encryption. Only `getDefaultDirectories()` covered indirectly. No tests for `addDirectory`, `deleteDirectory`, `getValidDirectories`, `exportBackup`, `restoreFromBackupIfEmpty`, `encryptData`, `decryptData`. |
| `DockerExecService` (33% methods) | MEDIUM | Network-dependent methods (`createExec`, `startExec`, `dockerPost`) hard to test without Docker. Input validation and `demuxStream` are well tested. |
| `SSH` (33% methods) | MEDIUM | Most methods require SSH2 extension + real connection. Constructor, validation, and error paths are covered. |
| `LogParser.parseString()` (50% methods) | **HIGH** | `parseFile()` and `parseLine()` are tested, but `parseString()` is only reached indirectly via `parseFile()`. Many formats are untested: syslog, nginx access, APK logs, APT/bootstrap, systemd journal, legacy multiline. Only FPL, simple, PHP error, and nginx error formats have explicit tests. |
| `LogScanner` (25% methods) | MEDIUM | `scanDirectory()` and `isLogFile()` are tested. `scanCommonDirectories()` and `getLogPatterns()` are not covered. |

### Methods never called in tests:

| Method | Class |
|--------|-------|
| `getValidDirectories()` | LogConfig |
| `cleanupAuto()` | LogConfig |
| `hasConfigurations()` | LogConfig |
| `getLogPatterns()` | LogScanner |
| `scanCommonDirectories()` | LogScanner |
| `connect()` (success path) | SSH |
| `readFile()` (success path) | SSH |
| `listFiles()` | SSH |
| `createExec()` | DockerExecService |
| `startExec()` | DockerExecService |
| `dockerPost()` | DockerExecService |

---

## Recommendations

1. **Immediately:** Remove hardcoded SSH credentials from `SSHControllerTest.php` (security issue)
2. **High priority:** Add `LogConfig` integration tests (CRUD operations on SQLite in temp dir, backup/restore round-trip, encryption/decryption)
3. **High priority:** Add `LogParser::parseString()` tests for uncovered formats (syslog, nginx access, APK, APT, systemd journal, legacy multiline)
4. **Medium:** Fix the failing `testGetFilesWithRealGlobLogFinderAndRelativePath` — create fixture or use temp dir
5. **Medium:** Replace hardcoded `/home/mariusz/PhpstormProjects/...` with dynamic path in both production code and tests
6. **Medium:** Delete duplicate test `testFinalizeMarksSetsupComplete` (typo in name + identical to existing test)
7. **Low:** Rename `testScanCommonDirectoriesScansExistingDirs` to match what it actually tests
8. **Low:** Rewrite `testMaxFileSize` to actually test `SecurityService` behavior

**Target coverage:** With `LogConfig` and `LogParser` format tests added, line coverage would rise from ~53% to ~70%+.
