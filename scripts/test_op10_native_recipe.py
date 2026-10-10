"""Offline receipt/audit query tests only. Never opens XAMPP or real credentials."""
import contextlib
import io
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import op10_native_recipe as tool


class NativeRecipeTest(unittest.TestCase):
    def test_default_never_spawns_sql_or_reads_agreement(self):
        with patch.object(tool, 'validate_receipt', side_effect=AssertionError('unexpected')), patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()) as out:
            self.assertEqual(tool.main([]), 0)
        self.assertIn('PREPARED_NO_SQL', out.getvalue())

    def test_missing_receipt_refuses_without_mysql(self):
        with patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()) as out:
            self.assertEqual(tool.main(['--audit', '--receipt=/private/tmp/not-a-receipt']), 1)
        self.assertIn('STOP_INSPECT_NO_RETRY', out.getvalue())
        self.assertNotIn('not-a-receipt', out.getvalue())

    def test_invalid_modes_are_refused(self):
        for args in [['--audit'], ['--check-receipt'], ['--audit', '--check-receipt', '--receipt=/private/tmp/none']]:
            with self.subTest(args=args), patch.object(tool, 'validate_receipt', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
                self.assertEqual(tool.main(args), 1)

    def test_readonly_tokens_allow_framing_but_never_writes_locks_or_exports(self):
        for q in ['SELECT 1', "SELECT SHA2(CONCAT('N;',';'),256)", 'SHOW CREATE TABLE `homya_local`.`archive_lots`']:
            self.assertEqual(tool.validate_query(q), q)
        for q in ['DELETE FROM homya_local.archive_lots', 'SELECT 1; DROP DATABASE homya_local', "SELECT 1 INTO OUTFILE '/tmp/a'", 'SELECT SLEEP(10)',
                  'SELECT GET_LOCK(\'x\',10)', "SELECT LOAD_FILE('/etc/passwd')", 'SELECT * FROM homya_local.archive_lots FOR UPDATE', 'SET GLOBAL max_allowed_packet=1']:
            with self.subTest(q=q), self.assertRaises(RuntimeError):
                tool.validate_query(q)

    @contextlib.contextmanager
    def fixture(self):
        with tempfile.TemporaryDirectory(prefix='homya-op10-native-offline-', dir='/private/tmp') as temp:
            root = Path(temp); qa = root/'qa';qa.mkdir(mode=0o700)
            plan = root/'plan.md';plan.write_text('plan test')
            art = root/'artifact';art.write_text('immutable')
            executable = root/'binary';executable.write_text('synthetic binary never run')
            registry = root/'registry.md'
            program_path = qa/'program.json';agreement_path = qa/'agreement.json';receipt_path = qa/'receipt.json'
            def write(p, data):
                p.write_text(json.dumps(data));p.chmod(0o600)
            program = dict(operation='OP-10-READONLY',native_read_authorized=False,activation_authorized=False,source_active='files',lot_key=tool.LOT,
                           reader='homya_local_reader',schema='homya_local',plan_sha256=tool.digest(plan),tools={str(executable):tool.digest(executable)},
                           artifacts={'artifact':tool.digest(art)},preservation={str(art):tool.digest(art)},admin_queries=[{'id':'safe','sql':'SELECT 1'}])
            write(program_path,program)
            registry.write_text('#### DECISION-044\nHOMYA OP-10-READONLY VALIDÉ '+tool.LOT+' homya_local_reader BASCULE EXCLUE '+tool.digest(plan)+' '+tool.digest(program_path))
            agreement=dict(operation='OP-10-READONLY',decision='DECISION-044',native_read_authorized=True,activation_authorized=False,registry_readback_verified=True,
                           registry_after_sha256=tool.digest(registry),programme_sha256=tool.digest(program_path),plan_sha256=tool.digest(plan))
            write(agreement_path,agreement)
            receipt=dict(operation='OP-10-READONLY',decision='DECISION-044',native_read_authorized=True,activation_authorized=False,
                         agreement_sha256=tool.digest(agreement_path),programme_sha256=tool.digest(program_path))
            write(receipt_path,receipt)
            with patch.multiple(tool, ROOT=root,QA=qa,PLAN=plan,PROGRAM=program_path,AGREEMENT=agreement_path,REGISTRY=registry,MYSQL=str(executable),PHP=str(executable)), patch.object(tool, 'EXPECTED_TOOLS', {str(executable)}):
                yield dict(root=root,artifact=art,binary=executable,plan=plan,program=program,program_path=program_path,registry=registry,agreement=agreement,agreement_path=agreement_path,receipt=receipt,receipt_path=receipt_path,write=write)

    def test_new_bound_native_receipt_is_accepted_on_synthetic_files_without_sql(self):
        with self.fixture() as f, patch.object(tool.subprocess,'run',return_value=type('Result',(),{'stdout':'-rw------- file\n'})()):
            self.assertEqual(tool.validate_receipt(f['receipt_path'])['schema'],'homya_local')

    def test_old_development_scope_or_activation_can_never_authorise_native(self):
        for key,value in [('decision','DECISION-042'),('decision','DECISION-043'),('activation_authorized',True),('native_read_authorized',False),('operation','OP-09')]:
            with self.subTest(key=key), self.fixture() as f, patch.object(tool.subprocess,'run',return_value=type('Result',(),{'stdout':''})()):
                f['receipt'][key]=value;f['write'](f['receipt_path'],f['receipt'])
                with self.assertRaises(RuntimeError): tool.validate_receipt(f['receipt_path'])

    def test_changed_plan_code_binary_or_registry_are_refused(self):
        for name in ['artifact','binary','plan','registry']:
            with self.subTest(name=name),self.fixture() as f,patch.object(tool.subprocess,'run',return_value=type('Result',(),{'stdout':''})()):
                f[name].write_text('changed')
                with self.assertRaises(RuntimeError): tool.validate_receipt(f['receipt_path'])

    def test_self_signed_replacement_program_is_not_an_approved_program(self):
        with self.fixture() as f,patch.object(tool.subprocess,'run',return_value=type('Result',(),{'stdout':''})()):
            f['program']['admin_queries']=[{'id':'replacement','sql':'SELECT 2'}];f['write'](f['program_path'],f['program'])
            f['agreement']['programme_sha256']=tool.digest(f['program_path']);f['write'](f['agreement_path'],f['agreement'])
            f['receipt']['agreement_sha256']=tool.digest(f['agreement_path']);f['receipt']['programme_sha256']=tool.digest(f['program_path']);f['write'](f['receipt_path'],f['receipt'])
            with self.assertRaisesRegex(RuntimeError,'REGISTRY_NATIVE_SCOPE'):tool.validate_receipt(f['receipt_path'])

    def test_closed_program_queries_and_http_matrix_are_read_only(self):
        program=json.loads(tool.PROGRAM.read_text())
        self.assertFalse(program['native_read_authorized'])
        self.assertFalse(program['activation_authorized'])
        self.assertEqual(len(program['admin_queries']),63)
        self.assertEqual(len(program['http_matrix']),78)
        for item in program['admin_queries']:self.assertEqual(tool.validate_query(item['sql']),item['sql'])
        self.assertTrue(all(item['path'].startswith(('/fr','/en','/de')) for item in program['http_matrix']))
        for field in ['persistent_sql','migrations','grants','database_exports','service_changes','active_site_changes']:self.assertEqual(program[field],[])

    def test_duplicate_keys_permissions_and_links_are_refused(self):
        with self.fixture() as f,patch.object(tool.subprocess,'run',return_value=type('Result',(),{'stdout':''})()):
            f['receipt_path'].write_text('{"operation":1,"operation":2}')
            with self.assertRaises(RuntimeError):tool.private_json(f['receipt_path'])
            f['receipt_path'].chmod(0o644)
            with self.assertRaises(RuntimeError):tool.private_json(f['receipt_path'])
            f['receipt_path'].chmod(0o600)
            os.link(f['receipt_path'],f['root']/'link')
            with self.assertRaises(RuntimeError):tool.private_json(f['receipt_path'])

    def server_row(self):
        return '\t'.join(['10.4.28-MariaDB','root@localhost','3307',tool.SOCKET,
                          '/Applications/XAMPP/xamppfiles/var/mysql/','127.0.0.1','OFF','16777216','0','0'])

    def test_tabular_server_identity_and_capacities_are_strict(self):
        self.assertEqual(tool.parse_server_response(self.server_row())['packet'],16777216)
        for index,value in [(0,'10.4.29-MariaDB'),(1,'root@remote'),(2,'3306'),(3,'/tmp/other.sock'),(4,'/other/'),
                            (5,'0.0.0.0'),(6,'ON'),(7,'1024'),(8,'1'),(9,'1'),(7,'NULL')]:
            values=self.server_row().split('\t');values[index]=value
            with self.subTest(index=index,value=value),self.assertRaises(RuntimeError):tool.parse_server_response('\t'.join(values))
        for row in [self.server_row()+'\n'+self.server_row(),self.server_row()+'\textra','{invalid json}',self.server_row().replace('\tOFF\t','\tOFF\r\t')]:
            with self.subTest(row=row),self.assertRaises(RuntimeError):tool.parse_server_response(row)

    def test_audit_mock_transport_success_has_no_raw_data_output(self):
        program={'admin_queries':[{'id':'server','sql':'SELECT 1'},{'id':'count','sql':'SELECT 1','expected':'1'}]}
        outputs=[type('Result',(),{'returncode':0,'stdout':self.server_row()+'\n'})(),type('Result',(),{'returncode':0,'stdout':'1\n'})()]
        with patch.object(tool,'validate_receipt',return_value=program),patch.object(tool.subprocess,'run',side_effect=outputs) as run:
            result=tool.audit('/synthetic-receipt')
        self.assertEqual(run.call_count,2)
        self.assertEqual(len(result['records']),2)
        self.assertNotIn('root@localhost',json.dumps(result))
        self.assertEqual(set(result['records'][0]),{'id','sha256','lines'})

    def test_invalid_server_transport_stops_before_any_other_query(self):
        program={'admin_queries':[{'id':'server','sql':'SELECT 1'},{'id':'next','sql':'SELECT 2'}]}
        for code,stdout in [(0,'{truncated'),(1,''),(0,self.server_row().replace('3307','3306'))]:
            with self.subTest(code=code,stdout=stdout),patch.object(tool,'validate_receipt',return_value=program),patch.object(tool.subprocess,'run',return_value=type('Result',(),{'returncode':code,'stdout':stdout})()) as run:
                with self.assertRaises(RuntimeError):tool.audit('/synthetic-receipt')
                self.assertEqual(run.call_count,1)


if __name__=='__main__':unittest.main()
