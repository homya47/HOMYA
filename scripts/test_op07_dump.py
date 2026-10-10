import io
import json
from pathlib import Path
import unittest
from unittest.mock import patch

import op07_dump as dump


class DumpTests(unittest.TestCase):
    def setUp(self):
        self.ddl = {t: t + '\tCREATE TABLE `' + t + '` (\n  `value` longtext DEFAULT NULL\n) ENGINE=InnoDB' for t in dump.TABLES}
        self.counts = dict.fromkeys(dump.TABLES, 1)

    def sample(self, value="'HOMYA'"):
        return '\n'.join(dump.ddl_sql(t, self.ddl[t]) + ';\nINSERT INTO `' + t + '` (`value`) VALUES (' + value + ');' for t in dump.TABLES)

    def validate(self, sql):
        return dump.validate_dump(io.StringIO(sql), self.ddl, self.counts)

    def test_exact_ddl_counts_and_sql_inside_data(self):
        sql = self.sample("'DROP DATABASE mysql; USE homya_local; /*comment*/ \\' lien\\n'")
        self.assertEqual(self.validate(sql)['counts'], self.counts)

    def test_actual_frozen_schema_dialect_includes_all_checks_and_foreign_keys(self):
        source = Path(__file__).resolve().parents[1]/'output/qa-import/op06-run-20261008T121403Z-a43af607-46fa-4eb1-8356-91138466d6c3/final-admin.json'
        ddl = json.loads(source.read_text())['physical_ddl']
        sql = '\n'.join(dump.ddl_sql(t, ddl[t])+';' for t in dump.TABLES)
        self.assertEqual(dump.validate_dump(io.StringIO(sql), ddl, dict.fromkeys(dump.TABLES, 0))['statement_count'], 5)
        bad = sql.replace('REFERENCES `archive_lots`', 'REFERENCES `homya_test`.`archive_lots`', 1)
        with self.assertRaisesRegex(RuntimeError, 'DUMP_DDL_MISMATCH'):
            dump.validate_dump(io.StringIO(bad), ddl, dict.fromkeys(dump.TABLES, 0))

    def test_all_native_session_statements(self):
        sets = [x for x in dump.SET_TEXT if x != 'SET character_set_client=utf8']
        sql = '\n'.join('/*!40101 ' + x + ' */;' for x in sets) + self.native_sample()
        self.assertEqual(self.validate(sql)['statement_count'], 25 + len(sets))

    def native_sample(self):
        save = '/*!40101 SET @saved_cs_client = @@character_set_client */;'
        charset = '/*!40101 SET character_set_client = utf8 */;'
        restore = '/*!40101 SET character_set_client = @saved_cs_client */;'
        return '\n'.join(save+'\n'+charset+'\n'+dump.ddl_sql(t, self.ddl[t])+';\n'+restore+
                         '\nINSERT INTO `'+t+'` (`value`) VALUES (\'HOMYA\');' for t in dump.TABLES)

    def test_native_utf8_ddl_sequence_does_not_allow_data_before_restore(self):
        accepted = self.native_sample()
        self.assertEqual(self.validate(accepted)['counts'], self.counts)
        save = '/*!40101 SET @saved_cs_client = @@character_set_client */;'
        charset = '/*!40101 SET character_set_client = utf8 */;'
        restore = '/*!40101 SET character_set_client = @saved_cs_client */;'
        for sql in [charset+self.sample(), accepted.replace(save, '', 1),
                    accepted.replace(restore, '', 1), accepted+save+charset,
                    accepted.replace(charset, charset+'/*!40101 SET NAMES utf8mb4 */;', 1),
                    accepted.replace(charset, charset.replace('40101', '40014'), 1),
                    accepted.replace(restore, restore.replace('40101', '40014'), 1),
                    accepted.replace(restore, 'INSERT INTO `archive_lots` (`value`) VALUES (\'x\');'+restore, 1)]:
            with self.subTest(sql=sql[:100]):
                with self.assertRaisesRegex(RuntimeError, 'DUMP_DDL_CHARSET_SEQUENCE'):
                    self.validate(sql)

    def test_literals_and_escaped_quotes(self):
        for value in ["NULL", "'a''b'", "'a\\0b\\Z\\r\\t\\b\\\\c'", '0x00ff', '_binary \'ab\'', '-12.35', '+42', '1.2e-3']:
            with self.subTest(value=value):
                self.validate(self.sample(value))

    def test_unapproved_instructions_and_client_commands(self):
        for extra in ['USE homya_local;', 'DROP TABLE `migrations`;', 'TRUNCATE `migrations`;',
                      'CREATE DATABASE other;', 'GRANT SELECT ON *.* TO user;',
                      'ALTER TABLE `migrations` DISABLE KEYS;', 'SET GLOBAL foreign_key_checks=0;',
                      'SET check_constraint_checks=0;', 'LOCK TABLES `archive_lots` WRITE;',
                      'DELIMITER ;;', '\\C utf8mb4;', '\\! touch /private/tmp/unsafe;',
                      '/*!40101 SET GLOBAL sql_mode=\'\' */;', '/*!99999 SET NAMES utf8mb4 */;',
                      '/*!40101 SET NAMES utf8mb4; DROP TABLE `migrations` */;', '-- comment\nSELECT 1;',
                      '/* comment */ SELECT 1;', '# comment\nSELECT 1;']:
            with self.subTest(extra=extra):
                with self.assertRaises(RuntimeError):
                    self.validate(self.sample() + extra)

    def test_table_and_schema_and_column_substitution(self):
        for sql in [self.sample().replace('INSERT INTO `archive_lots`', 'INSERT INTO `homya_test`.`archive_lots`'),
                    self.sample().replace('`value`) VALUES', '`password`) VALUES'),
                    self.sample().replace('INSERT INTO `archive_lots`', 'INSERT INTO `mysql`'),
                    self.sample().replace('ENGINE=InnoDB', 'ENGINE=MyISAM', 1),
                    self.sample().replace('INSERT INTO', 'REPLACE INTO', 1)]:
            with self.subTest(sql=sql[:30]):
                with self.assertRaises(RuntimeError):
                    self.validate(sql)

    def test_expressions_extended_insert_and_suffixes_rejected(self):
        for value in ['LOAD_FILE(\'/etc/passwd\')', 'CONCAT(\'a\',\'b\')', '0xabc',
                      '(SELECT 1)', '@@global.sql_mode', '1+1', 'DEFAULT', '0xZZ', "'\\q'"]:
            with self.subTest(value=value):
                with self.assertRaises(RuntimeError):
                    self.validate(self.sample(value))
        for sql in [self.sample().replace("VALUES ('HOMYA')", "VALUES ('HOMYA'),('other')", 1),
                    self.sample().replace("VALUES ('HOMYA')", "VALUES ('HOMYA') ON DUPLICATE KEY UPDATE value=1", 1)]:
            with self.assertRaises(RuntimeError):
                self.validate(sql)

    def test_truncated_or_unterminated_or_empty_dump(self):
        for ending in ['', "INSERT INTO `migrations` (`value`) VALUES ('a',;", "'unfinished", '/*!40101 SET NAMES utf8mb4', ';']:
            with self.subTest(ending=ending):
                with self.assertRaises(RuntimeError):
                    self.validate(ending)
        with self.assertRaises(RuntimeError):
            self.validate(self.sample()[:-1])
        with self.assertRaises(RuntimeError):
            self.validate(self.sample().replace("VALUES ('HOMYA');", "VALUES ('HOMYA',;", 1))

    def test_complete_tables_counts_order_and_duplicate_create(self):
        cases = [self.sample().replace("INSERT INTO `archive_lots` (`value`) VALUES ('HOMYA');", ''),
                 self.sample() + dump.ddl_sql('migrations', self.ddl['migrations']) + ';',
                 "INSERT INTO `migrations` (`value`) VALUES ('x');" + self.sample(),
                 self.sample().split('CREATE TABLE `migrations`')[0]]
        for sql in cases:
            with self.assertRaises(RuntimeError):
                self.validate(sql)

    def test_packet_limit_is_on_encoded_bytes_not_characters(self):
        with patch.object(dump, 'PACKET', 10):
            with self.assertRaisesRegex(RuntimeError, 'DUMP_STATEMENT_PACKET'):
                list(dump.statements(io.StringIO("'éééé';")))

    def test_split_across_chunks_multiline_escaped_and_doubled_quotes(self):
        class TinyStream(io.StringIO):
            def read(self, _size):
                return super().read(1)
        sql = self.sample("'a;\n\\\'b''c'")
        self.assertEqual(dump.validate_dump(TinyStream(sql), self.ddl, self.counts)['counts'], self.counts)

    def test_null_empty_text_bytes_and_boundaries_have_distinct_hashes(self):
        cases = [(None,), (b'',), (b'NULL',), (b'\0',), (b'a', b'bc'), (b'ab', b'c')]
        hashes = {dump.hash_rows([row])['sha256'] for row in cases}
        self.assertEqual(len(hashes), len(cases))
        self.assertNotEqual(dump.hash_rows([(b'1',),(b'2',)]), dump.hash_rows([(b'2',),(b'1',)]))


if __name__ == '__main__':
    unittest.main()
