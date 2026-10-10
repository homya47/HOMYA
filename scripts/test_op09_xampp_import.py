"""OP-09 authority, audit, backup and failure boundaries. All SQL/native IO uses doubles."""
from contextlib import ExitStack
import copy
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import unittest
from unittest.mock import Mock, patch

import op09_xampp_import as op


class Op09Tests(unittest.TestCase):
    def receipt_fixture(self, stack, directory):
        root = Path(directory).resolve(); qa = root/'qa'; qa.mkdir(mode=0o700)
        plan = root/'plan.md'; plan.write_text('reviewed plan')
        code = root/'tool.py'; code.write_text('reviewed tool')
        tools = [root/x for x in ('mysql', 'dump', 'php', 'python')]
        for p in tools: p.write_text('native binary double')
        reg = root/'registry.md'
        reg.write_text('#### DECISION-999\n| Projet / périmètre | HOMYA — OP-09 exécution locale |\n| Statut | VALIDÉ |\n'+op.LOT+' homya_local\n')
        agreement = qa/'agreement.json'
        a = dict(operation='OP-09', schema='homya_local', lot_key=op.LOT, decision='DECISION-999', sql_authorized=True,
                 execution_authorized=True, registry_readback_verified=True, registry_after_sha256=op.digest(reg))
        agreement.write_text(json.dumps(a)); agreement.chmod(0o600)
        receipt = qa/'receipt.json'
        r = dict(operation='OP-09', schema='homya_local', lot_key=op.LOT, decision='DECISION-999', execution_authorized=True,
                 agreement_sha256=op.digest(agreement), plan_sha256=op.digest(plan), artifacts={'tool.py': op.digest(code)},
                 tools={str(p): op.digest(p) for p in tools})
        program = qa/'program.json'
        program.write_text(json.dumps({k:r[k] for k in ('operation','schema','lot_key','plan_sha256','artifacts','tools')} | {'execution_authorized':False}));program.chmod(0o600)
        a.update(plan_sha256=r['plan_sha256'],programme_sha256=op.digest(program));agreement.write_text(json.dumps(a))
        r['agreement_sha256']=op.digest(agreement)
        receipt.write_text(json.dumps(r)); receipt.chmod(0o600)
        for name, value in [('ROOT', root), ('QA', qa), ('PLAN', plan), ('PROGRAM',program), ('AGREEMENT', agreement), ('ARTIFACTS', ('tool.py',)),
                            *zip(('MYSQL', 'DUMP', 'PHP', 'PYTHON'), map(str, tools))]:
            stack.enter_context(patch.object(op, name, value))
        stack.enter_context(patch.object(op.prior, 'REGISTRY', reg))
        stack.enter_context(patch.object(op, 'protected_files', return_value={'unchanged': 'hash'}))
        return receipt, r, a

    def test_receipt_scope_rejects_old_operation_and_development_authority(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            p, r, a = self.receipt_fixture(stack, d)
            self.assertEqual(r, op.validate_receipt(p))
            for key, wrong in [('operation', 'OP-08'), ('schema', 'homya_test'), ('lot_key', '0'*64),
                               ('decision', 'DECISION-040'), ('execution_authorized', False),
                               ('artifacts', {}), ('tools', {}), ('plan_sha256', '0'*64)]:
                p.write_text(json.dumps({**r, key: wrong}))
                with self.assertRaises(RuntimeError): op.validate_receipt(p)
            a['sql_authorized'] = False; op.AGREEMENT.write_text(json.dumps(a))
            p.write_text(json.dumps({**r, 'agreement_sha256': op.digest(op.AGREEMENT)}))
            with self.assertRaisesRegex(RuntimeError, 'EXECUTION_AGREEMENT_REQUIRED'): op.validate_receipt(p)

    def test_receipt_security_duplicate_keys_links_and_drift_are_refused(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            p, r, _ = self.receipt_fixture(stack, d)
            p.chmod(0o644)
            with self.assertRaises(RuntimeError): op.validate_receipt(p)
            p.chmod(0o600); p.write_text('{"operation":"OP-09","operation":"OP-09"}')
            with self.assertRaisesRegex(RuntimeError, 'DUPLICATE_JSON_KEY'): op.validate_receipt(p)
            p.write_text(json.dumps(r)); link = op.QA/'link.json'; link.symlink_to(p)
            with self.assertRaises(RuntimeError): op.validate_receipt(link)
            (op.ROOT/'tool.py').write_text('changed')
            with self.assertRaisesRegex(RuntimeError, 'ARTIFACT_CHANGED'): op.validate_receipt(p)

    def test_registry_wrong_scope_is_refused_even_with_matching_hash(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            p, r, a = self.receipt_fixture(stack, d)
            op.prior.REGISTRY.write_text('#### DECISION-999\n| Projet / périmètre | OTHER OP-09 exécution |\nVALIDÉ '+op.LOT+' homya_local')
            a['registry_after_sha256'] = op.digest(op.prior.REGISTRY); op.AGREEMENT.write_text(json.dumps(a))
            p.write_text(json.dumps({**r, 'agreement_sha256': op.digest(op.AGREEMENT)}))
            with self.assertRaisesRegex(RuntimeError, 'REGISTRY_EXECUTION_SCOPE'): op.validate_receipt(p)

    def test_self_updated_receipt_cannot_change_reviewed_program(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            p,r,a=self.receipt_fixture(stack,d)
            (op.ROOT/'tool.py').write_text('modified tool')
            r['artifacts']['tool.py']=op.digest(op.ROOT/'tool.py');p.write_text(json.dumps(r))
            with self.assertRaisesRegex(RuntimeError,'APPROVED_PROGRAM_MISMATCH'):op.validate_receipt(p)
            program=json.loads(op.PROGRAM.read_text());program['artifacts']=r['artifacts'];op.PROGRAM.write_text(json.dumps(program))
            with self.assertRaisesRegex(RuntimeError,'APPROVED_PROGRAM_REQUIRED'):op.validate_receipt(p)

    def test_no_process_before_authority_and_no_admin_writes(self):
        with patch.object(op, 'validate_receipt', side_effect=RuntimeError('NO_AUTH')), patch.object(op.subprocess, 'run') as run:
            with self.assertRaisesRegex(RuntimeError, 'NO_AUTH'): op.sql('SELECT 1', 'missing')
            run.assert_not_called()
        with patch.object(op, 'validate_receipt'), patch.object(op.subprocess, 'run') as run:
            for q in ['DELETE FROM x', 'GRANT SELECT ON x TO y', "SELECT 1; DROP TABLE x", "SELECT 1 INTO OUTFILE 'x'"]:
                with self.assertRaises(RuntimeError): op.sql(q, 'receipt')
            run.assert_not_called()

    def test_readonly_sql_allows_quoted_semicolon_and_expunges_errors(self):
        with patch.object(op, 'validate_receipt'), patch.object(op.subprocess, 'run', return_value=Mock(returncode=0, stdout='ok', stderr='')) as run:
            self.assertEqual('ok', op.sql("SELECT SHA2(CONCAT('N;', 'B0:;'),256)", 'receipt'))
            self.assertIn('--no-defaults', run.call_args.args[0])
            self.assertIn('--socket='+op.base.SOCKET, run.call_args.args[0])
            self.assertNotIn('--execute', ' '.join(run.call_args.args[0]))
            run.return_value = Mock(returncode=1, stdout='synthetic-secret', stderr='ERROR 1045 synthetic-secret')
            with self.assertRaisesRegex(RuntimeError, '^XAMPP_SQL_ERROR_1045$'): op.sql('SELECT 1', 'receipt')

    def test_expired_deadline_prevents_native_sql(self):
        with patch.object(op, 'validate_receipt'), patch.object(op, 'ACTIVE_DEADLINE', time.monotonic()-1), patch.object(op.subprocess, 'run') as run:
            with self.assertRaisesRegex(RuntimeError, 'OPERATION_DEADLINE'): op.sql('SELECT 1', 'receipt')
            run.assert_not_called()

    def test_native_dump_targets_only_five_local_tables_and_contains_no_write_flags(self):
        cmd = op.dump_command()
        self.assertEqual(['homya_local', *op.TABLES], cmd[-6:])
        for flag in ('--single-transaction', '--skip-lock-tables', '--skip-add-drop-table', '--skip-triggers', '--skip-events', '--skip-routines'):
            self.assertIn(flag, cmd)
        self.assertNotIn('--databases', cmd); self.assertNotIn('--all-databases', cmd)

    def audit_fixture(self):
        data = op.history.expected_metadata(12, 'final')
        def native(q, receipt):
            if 'FROM information_schema.COLUMN_PRIVILEGES' in q: return '\n'.join(json.dumps(x) for x in data['column_privileges'])
            if 'FROM mysql.columns_priv' in q: return '\n'.join(json.dumps(x) for x in data['column_records'])
            if q.startswith('SHOW CREATE'): return 'fake-ddl'
            if 'FROM homya_local.migrations' in q: return '\n'.join(json.dumps(dict(id=i,migration=m,batch=1)) for i,m in enumerate(op.base.MIGRATIONS,1))
            if q.startswith('SELECT COUNT(*)'): return str(op.INITIAL_COUNTS[q.split('`.`')[-1].split('`')[0]])
            if 'REFERENTIAL_CONSTRAINTS' in q: return 'homya_local\tRESTRICT\tRESTRICT\t4'
            if 'FROM homya_local.archive_lots' in q: return op.LOT+'\tverified'
            raise AssertionError(q)
        bare = {k: copy.deepcopy(v) for k,v in data.items() if k not in ('column_privileges','column_records')}
        return bare, native

    def test_complete_audit_refuses_nonempty_before_and_wrong_after_counts(self):
        bare, native = self.audit_fixture()
        reference = copy.deepcopy(op.history.reference())
        reference['physical_ddl'] = dict.fromkeys(op.TABLES, 'fake-ddl')
        with patch.object(op.base, 'inspect', return_value=bare), patch.object(op, 'sql', side_effect=native), \
             patch.object(op.history, 'reference', return_value=reference), patch.object(op.history, 'restore_rights'):
            before = op.inspect('receipt')
            self.assertEqual(op.INITIAL_COUNTS, before['counts'])
            self.assertNotIn('connection_id', before['metadata']['server'])
            with self.assertRaisesRegex(RuntimeError, 'LOCAL_COUNTS_OR_COLLISION'): op.inspect('receipt', loaded=True)

    def test_loaded_audit_requires_exact_lot_and_keeps_metadata_scope(self):
        bare,native=self.audit_fixture();reference=copy.deepcopy(op.history.reference())
        reference['physical_ddl']=dict.fromkeys(op.TABLES,'fake-ddl')
        def loaded(q,r):
            if q.startswith('SELECT COUNT(*)'):return str(op.FINAL_COUNTS[q.split('`.`')[-1].split('`')[0]])
            return native(q,r)
        with patch.object(op.base,'inspect',side_effect=lambda:copy.deepcopy(bare)), patch.object(op,'sql',side_effect=loaded), \
             patch.object(op.history,'reference',return_value=reference),patch.object(op.history,'restore_rights'):
            result=op.inspect('receipt',loaded=True)
            self.assertEqual(op.FINAL_COUNTS,result['counts'])
            self.assertEqual(52,len(result['metadata']['privileges']))
            with patch.object(op,'sql',side_effect=lambda q,r:op.LOT+'\tfailed' if 'FROM homya_local.archive_lots' in q else loaded(q,r)):
                with self.assertRaisesRegex(RuntimeError,'LOT_STATE'):op.inspect('receipt',loaded=True)

    def test_historical_definitions_are_restored_after_audit_failure(self):
        old_base,old_history=op.base.sql,op.history.sql
        with self.assertRaisesRegex(RuntimeError,'SYNTHETIC'):
            with op.readonly_definitions('receipt'):
                self.assertIsNot(old_base,op.base.sql)
                raise RuntimeError('SYNTHETIC')
        self.assertIs(old_base,op.base.sql);self.assertIs(old_history,op.history.sql)

    def test_helper_ticket_is_bound_to_one_execution_and_consumed_once(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            p, r, _ = self.receipt_fixture(stack, d)
            run = op.QA/'op09-run-synthetic'; run.mkdir(mode=0o700)
            op.save(op.QA/('.op09-consumed-'+r['decision']+'.json'), dict(decision=r['decision'],run=str(run),receipt_sha256=op.digest(p),wrapper_pid=123))
            op.save(run/'ticket-import.json', dict(mode='import',receipt_sha256=op.digest(p),wrapper_pid=123))
            with self.assertRaisesRegex(RuntimeError, 'EXECUTION_MARKER'): op.helper_ticket(p, run, 'import', 124)
            op.helper_ticket(p, run, 'import', 123)
            with self.assertRaises(FileExistsError): op.helper_ticket(p, run, 'import', 123)
            self.assertFalse((run/'used-read.json').exists())

    def workflow_fixture(self, stack, d):
        root = Path(d).resolve(); (root/'bootstrap/cache').mkdir(parents=True); (root/'database/schema').mkdir(parents=True)
        run = root/'run'; run.mkdir(); (root/'dump').write_text('backup')
        stack.enter_context(patch.object(op, 'ROOT', root))
        mocks = {}
        for name, value in [('validate_receipt', {}), ('protected_files', {'file':'same'}), ('access_files',{'import':'fixture/import','reader':'fixture/reader'}),
                            ('inspect', {'audit':'same'}), ('preservation',{'rows':'same'}), ('originals', {'source':'same'}),
                            ('backup', {'path':'dump','sha256':op.digest(root/'dump')}), ('php_call',{}), ('save',None)]:
            mocks[name] = stack.enter_context(patch.object(op,name,return_value=value))
        stack.enter_context(patch.object(op.prior,'runtime_witnesses',return_value={'runtime':'same'}))
        stack.enter_context(patch.object(op.prior,'verify_media',return_value={'capsule':'same'}))
        return root, run, mocks

    def test_backup_precedes_one_import_and_independent_reader(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            root, run, mocks = self.workflow_fixture(stack,d); events=[]
            mocks['backup'].side_effect=lambda *a: events.append('backup') or {'path':'dump','sha256':op.digest(root/'dump')}
            mocks['php_call'].side_effect=lambda mode,*a: events.append(mode) or {}
            op.workflow('receipt',run,time.monotonic()+100)
            self.assertEqual(['backup','import','read'],events)
            self.assertEqual(2,mocks['php_call'].call_count)
            self.assertTrue(mocks['inspect'].call_args.kwargs['loaded'])
            self.assertTrue(any(c.args[0].name=='status.json' for c in mocks['save'].call_args_list))

    def test_every_boundary_error_stops_without_retry_or_success(self):
        for boundary in ('backup','import','read','final','preservation'):
            with self.subTest(boundary=boundary), tempfile.TemporaryDirectory() as d, ExitStack() as stack:
                root, run, mocks = self.workflow_fixture(stack,d)
                if boundary=='backup': mocks['backup'].side_effect=RuntimeError('SYNTHETIC')
                if boundary in ('import','read'): mocks['php_call'].side_effect=([RuntimeError('SYNTHETIC')] if boundary=='import' else [{},RuntimeError('SYNTHETIC')])
                if boundary=='final': mocks['inspect'].side_effect=[{}, {}, RuntimeError('SYNTHETIC')]
                if boundary=='preservation': mocks['preservation'].side_effect=[{'rows':'same'},{'rows':'same'},{'rows':'drift'}]
                with self.assertRaises(RuntimeError): op.workflow('receipt',run,time.monotonic()+100)
                self.assertLessEqual(mocks['php_call'].call_count,2)
                self.assertFalse(any(c.args[0].name=='status.json' for c in mocks['save'].call_args_list))

    def test_single_execution_marker_blocks_repetition_after_any_failure(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            root=Path(d).resolve(); p=root/'receipt.json'; p.write_text('{}')
            stack.enter_context(patch.object(op,'QA',root))
            stack.enter_context(patch.object(op,'validate_receipt',return_value={'decision':'DECISION-999'}))
            workflow=stack.enter_context(patch.object(op,'workflow',side_effect=RuntimeError('SYNTHETIC')))
            with self.assertRaisesRegex(RuntimeError,'SYNTHETIC'): op.execute(p)
            with self.assertRaises(FileExistsError): op.execute(p)
            workflow.assert_called_once(); self.assertEqual(1,len(list(root.glob('op09-run-*'))))
            report=json.loads(next(root.glob('op09-run-*/failure.json')).read_text())
            self.assertFalse(report['retry']); self.assertFalse(report['cleanup']); self.assertFalse(report['activated'])
            self.assertIsNone(op.ACTIVE_DEADLINE)

    def test_common_op08_lock_prevents_start_and_does_not_consume_authority(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            root=Path(d).resolve(); lock=root/'.op08.lock'; lock.touch(mode=0o600)
            stack.enter_context(patch.object(op,'QA',root)); stack.enter_context(patch.object(op,'validate_receipt',return_value={'decision':'DECISION-999'}))
            with lock.open('r+') as held:
                op.fcntl.flock(held,op.fcntl.LOCK_EX|op.fcntl.LOCK_NB)
                with self.assertRaises(BlockingIOError): op.execute(root/'receipt.json')
            self.assertEqual([],list(root.glob('.op09-consumed-*')))

    def test_no_receipt_cli_and_helper_default_refuse_without_bootstrap(self):
        for command in [[op.PYTHON,str(op.ROOT/'scripts/op09_xampp_import.py'),'--execute'],
                        [op.PHP,str(op.ROOT/'scripts/op09_catalogue.php'),'--import'],
                        [op.PHP,str(op.ROOT/'scripts/op09_catalogue.php'),'--read','--receipt=absent','--run-dir=absent','--access=absent']]:
            r=subprocess.run(command,capture_output=True,text=True,timeout=15)
            self.assertEqual(1,r.returncode); self.assertEqual('',r.stderr)
            self.assertEqual('STOP_INSPECT_NO_RETRY',json.loads(r.stdout)['status'])

    def test_backup_failure_never_exports_before_valid_authority(self):
        with patch.object(op,'validate_receipt',side_effect=RuntimeError('NO_AUTH')), patch.object(op.subprocess,'Popen') as process:
            with self.assertRaisesRegex(RuntimeError,'NO_AUTH'): op.backup('absent',time.monotonic()+10,{})
            process.assert_not_called()

    def backup_fixture(self, stack, d, corrupt=False):
        root=Path(d).resolve(); parent=root/'backups'; parent.mkdir(mode=0o700)
        stack.enter_context(patch.object(op,'ROOT',root)); stack.enter_context(patch.object(op,'BACKUPS',parent/'op09'))
        stack.enter_context(patch.object(op,'validate_receipt'))
        ddl={t:t+'\tCREATE TABLE `'+t+'` (\n  `id` int\n)' for t in op.TABLES}
        ddl['migrations']='migrations\tCREATE TABLE `migrations` (\n  `id` int,\n  `migration` text,\n  `batch` int\n)'
        audit={'physical_ddl':ddl}
        text='\n'.join(v.split('\t',1)[1]+';' for v in ddl.values())+'\n'
        text+='\n'.join("INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ("+str(i)+",'"+(m+'wrong' if corrupt and i==1 else m)+"',1);" for i,m in enumerate(op.base.MIGRATIONS,1))
        def process(*a,**kw):
            kw['stdout'].write(text.encode());kw['stdout'].flush()
            return Mock(poll=Mock(return_value=0),returncode=0,communicate=Mock(return_value=(b'',b'')))
        stack.enter_context(patch.object(op,'inspect',return_value=audit))
        popen=stack.enter_context(patch.object(op.subprocess,'Popen',side_effect=process))
        return root,audit,popen

    def test_preimport_dump_checks_exact_journal_and_freezes_file(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            root,audit,process=self.backup_fixture(stack,d)
            proof=op.backup('receipt',time.monotonic()+100,audit)
            self.assertEqual(op.INITIAL_COUNTS,proof['counts']);self.assertTrue(proof['journal_exact'])
            self.assertFalse(proof['restoration_tested']);self.assertEqual(0o400,(root/proof['path']).stat().st_mode&0o777)
            self.assertEqual(op.digest(root/proof['path']),proof['sha256']);process.assert_called_once()

    def test_equal_dump_counts_with_wrong_journal_value_are_refused(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            root,audit,process=self.backup_fixture(stack,d,corrupt=True)
            with self.assertRaisesRegex(RuntimeError,'DUMP_JOURNAL_VALUES'):op.backup('receipt',time.monotonic()+100,audit)
            self.assertEqual([],list(root.glob('backups/op09/*/verification.json')))

    def test_insufficient_space_blocks_native_dump(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            _,audit,process=self.backup_fixture(stack,d)
            stack.enter_context(patch.object(op.shutil,'disk_usage',return_value=Mock(free=1)))
            with self.assertRaisesRegex(RuntimeError,'BACKUP_SPACE'):op.backup('receipt',time.monotonic()+100,audit)
            process.assert_not_called()

    def test_backup_audit_drift_refuses_dml_readiness(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            root,audit,_=self.backup_fixture(stack,d)
            stack.enter_context(patch.object(op,'inspect',return_value={'different':'state'}))
            with self.assertRaisesRegex(RuntimeError,'PREIMPORT_CHANGED_DURING_DUMP'):op.backup('receipt',time.monotonic()+100,audit)
            self.assertEqual([],list(root.glob('backups/op09/*/verification.json')))

    def test_php_timeout_kills_only_own_process_group_and_never_retries(self):
        with tempfile.TemporaryDirectory() as d, ExitStack() as stack:
            root=Path(d).resolve();receipt=root/'receipt.json';receipt.write_text('{}')
            stack.enter_context(patch.object(op,'validate_receipt'))
            process=Mock(pid=4321,communicate=Mock(side_effect=[subprocess.TimeoutExpired('synthetic',1),('','')]))
            popen=stack.enter_context(patch.object(op.subprocess,'Popen',return_value=process))
            kill=stack.enter_context(patch.object(op.os,'killpg'))
            with self.assertRaisesRegex(RuntimeError,'PHP_TIMEOUT_STATUS_TO_INSPECT'):
                op.php_call('import',receipt,root,'synthetic/import',time.monotonic()+100)
            kill.assert_called_once_with(4321,op.signal.SIGTERM);popen.assert_called_once()
            self.assertTrue(popen.call_args.kwargs['start_new_session'])


if __name__=='__main__':
    unittest.main()
