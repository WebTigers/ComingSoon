<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace ComingSoon\Tests\Support;

use Comingsoon_Content;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tiger_Model_Org;
use Tiger_Model_Table;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Db;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * Base for ComingSoon tests that need core's real `config` + `media` tables.
 *
 * ComingSoon ships no tables of its own: its settings are core config rows and its background is a core
 * media row. The two tables are built once per process from tiger-core's OWN migrations (resolved from the
 * same checkout the bootstrap loaded) after dropping any previous copy, so the schema can't drift from
 * core. Each test runs in a transaction rolled back in tearDown. The site org (a per-request static in
 * core) and the module's per-request config cache are reset around every test.
 *
 * Self-skips when TIGER_TEST_DB_NAME is unset:
 *   TIGER_TEST_DB_NAME (required)  TIGER_TEST_DB_SOCKET (unix socket; overrides host/port)
 *   TIGER_TEST_DB_HOST/_PORT (default 127.0.0.1/3306)  TIGER_TEST_DB_USER/_PASS (default root/'')
 *
 * NEVER point TIGER_TEST_DB_* at a dev or prod database — setUp DROPS the `config` and `media` tables.
 */
abstract class IntegrationTestCase extends TestCase
{
    /** Core migrations whose tables ComingSoon reads, in apply order. */
    private const CORE_MIGRATIONS = ['0009_create_config.php', '0018_create_media.php', '0040_media_description.php'];

    private static ?Zend_Db_Adapter_Abstract $sharedDb = null;
    private static bool $schemaReady = false;

    protected Zend_Db_Adapter_Abstract $db;

    private ReflectionProperty $siteProp;

    protected function setUp(): void
    {
        parent::setUp();

        $name = getenv('TIGER_TEST_DB_NAME');
        if ($name === false || $name === '') {
            $this->markTestSkipped('Integration DB not configured (set TIGER_TEST_DB_NAME to run).');
        }

        $this->db = self::adapter((string) $name);
        $this->ensureSchema();

        Zend_Registry::getInstance()->offsetExists('Zend_Config') && Zend_Registry::getInstance()->offsetUnset('Zend_Config');
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
        Tiger_Model_Table::setActor(null);
        Tiger_Model_Table::setOrg('');
        $this->siteProp = new ReflectionProperty(Tiger_Model_Org::class, '_siteOrgId');
        $this->siteProp->setValue(null, null);
        Comingsoon_Content::resetCache();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            try { $this->db->rollBack(); } catch (\Throwable $e) { /* test closed the txn — fine */ }
        }
        Zend_Auth::getInstance()->clearIdentity();
        Tiger_Model_Table::setActor(null);
        Tiger_Model_Table::setOrg('');
        if (isset($this->siteProp)) { $this->siteProp->setValue(null, null); }
        Comingsoon_Content::resetCache();
        unset($_SERVER['HTTP_HOST']);
        parent::tearDown();
    }

    /** Write a signed-in identity (non-persistent storage) carrying an org. */
    protected function login(string $orgId, string $userId = 'user-1', string $role = 'admin'): void
    {
        Zend_Auth::getInstance()->getStorage()->write((object) [
            'org_id' => $orgId, 'user_id' => $userId, 'role' => $role,
        ]);
    }

    /** Pin the public site org for this request (what a host-resolving bootstrap or tiger.site.org_id sets). */
    protected function siteOrg(string $orgId): void
    {
        Tiger_Model_Org::setSiteOrgId($orgId);
        Comingsoon_Content::resetCache();
    }

    /** Every config row, as "scope|scope_id|key=value" strings (for "nothing was written" assertions). */
    protected function configRows(): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT scope, scope_id, config_key, config_value FROM `config` ORDER BY scope, scope_id, config_key') as $r) {
            $out[] = $r['scope'] . '|' . $r['scope_id'] . '|' . $r['config_key'] . '=' . $r['config_value'];
        }
        return $out;
    }

    private static function adapter(string $name): Zend_Db_Adapter_Abstract
    {
        if (self::$sharedDb === null) {
            $cfg = [
                'dbname'   => $name,
                'username' => getenv('TIGER_TEST_DB_USER') ?: 'root',
                'password' => getenv('TIGER_TEST_DB_PASS') ?: '',
                'charset'  => 'utf8mb4',
            ];
            $socket = getenv('TIGER_TEST_DB_SOCKET');
            if ($socket) {
                $cfg['unix_socket'] = $socket;
            } else {
                $cfg['host'] = getenv('TIGER_TEST_DB_HOST') ?: '127.0.0.1';
                $cfg['port'] = (int) (getenv('TIGER_TEST_DB_PORT') ?: 3306);
            }
            self::$sharedDb = Zend_Db::factory('Pdo_Mysql', $cfg);
            Zend_Db_Table_Abstract::setDefaultAdapter(self::$sharedDb);
            Zend_Registry::set('Zend_Db', self::$sharedDb);
        }
        return self::$sharedDb;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $migrations = [];
        foreach (self::CORE_MIGRATIONS as $file) {
            $migrations[] = require TIGER_CORE_PATH . '/migrations/' . $file;
        }
        foreach (array_reverse($migrations) as $m) {
            foreach ($m['down'] as $sql) { try { $this->db->query($sql); } catch (\Throwable $e) { /* not there yet */ } }
        }
        foreach ($migrations as $m) {
            foreach ($m['up'] as $sql) { $this->db->query($sql); }
        }
        self::$schemaReady = true;
    }
}
