"""Targeted OP-08 resumption checks. No native SQL or real private access."""
from contextlib import ExitStack
import json
from pathlib import Path
import subprocess
import tempfile
import time
import unittest
from unittest.mock import patch

import op08_resume as resume
import test_op08_xampp_admin as initial_tests


class Op08ResumptionTests(unittest.TestCase):
    def fixture(self, stack, directory):
        root, mocks = initial_tests.Op08Tests().workflow_fixture(stack, directory)
        previous = root/'previous'; previous.mkdir(); (previous/'runtime-before.json').write_text(json.dumps({'runtime':'same'}))
        stack.enter_context(patch.object(resume, 'ROOT', root)); stack.enter_context(patch.object(resume, 'QA', root/'qa'))
        stack.enter_context(patch.object(resume, 'PREVIOUS', previous))
        stack.enter_context(patch.object(resume, 'validate_receipt'))
        state = {'protected_after':{'hash':'same'}, 'media':{'media':'same'}}
        stack.enter_context(patch.object(resume, 'stopped_state', return_value=state)); stack.enter_context(patch.object(resume, 'save', mocks['save']))
        return root, mocks

    def test_corrected_statement_includes_existing_create_without_new_rights(self):
        self.assertEqual(11, len(resume.GRANTS))
        for q in resume.GRANTS[:2]:
            self.assertTrue(q.startswith('GRANT CREATE, REFERENCES ON `homya_local`.`archive_'))
            self.assertNotIn('*', q); self.assertNotIn('GRANT OPTION', q)
        self.assertEqual(resume.initial.GRANTS[3:], resume.GRANTS[2:])
        # CREATE is already in the initial baseline; no privilege beyond REFERENCES is added.
        baseline = resume.initial.expected_metadata(1, 'journal')
        for table in ('archive_lots', 'archive_categories'):
            self.assertTrue(any(p['schema']=='homya_local' and p['table']==table and p['privilege']=='CREATE' for p in baseline['privileges']))
        self.assertEqual(52, len(resume.initial.expected_metadata(12, 'final')['privileges']))

    def test_only_remaining_actions_are_run_once(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            root, mocks = self.fixture(stack, directory)
            resume.workflow('receipt', root, time.monotonic()+100)
            self.assertEqual(list(resume.GRANTS), [c.args[0] for c in mocks['sql'].call_args_list])
            self.assertEqual(5, mocks['php_call'].call_count)
            self.assertIn('--journal', mocks['php_call'].call_args_list[0].args[0])
            self.assertTrue(mocks['php_call'].call_args_list[1].kwargs['migration'])
            self.assertEqual((1,'journal','receipt'), mocks['inspect'].call_args_list[0].args)
            self.assertFalse(any('--create-journal' in c.args[0] or '--empty' in c.args[0] for c in mocks['php_call'].call_args_list))
            self.assertNotIn(resume.initial.GRANTS[0], [c.args[0] for c in mocks['sql'].call_args_list])

    def test_every_write_failure_stops_without_retry(self):
        for i in range(11):
            with self.subTest(grant=i), tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
                root, mocks = self.fixture(stack, directory); mocks['sql'].side_effect = ['']*i+[RuntimeError('STOP')]
                with self.assertRaises(RuntimeError): resume.workflow('receipt', root, time.monotonic()+100)
                self.assertEqual(i+1, mocks['sql'].call_count)
        for i in range(5):
            with self.subTest(php=i), tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
                root, mocks = self.fixture(stack, directory); mocks['php_call'].side_effect = [None]*i+[RuntimeError('STOP')]
                with self.assertRaises(RuntimeError): resume.workflow('receipt', root, time.monotonic()+100)
                self.assertEqual(i+1, mocks['php_call'].call_count)

    def test_preflight_data_and_runtime_drift_prevent_all_writes(self):
        for boundary in ('runtime','data','journal'):
            with self.subTest(boundary=boundary), tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
                root, mocks = self.fixture(stack, directory)
                if boundary=='runtime': (resume.PREVIOUS/'runtime-before.json').write_text('{}')
                if boundary=='data': mocks['preservation'].return_value={}
                if boundary=='journal': mocks['inspect'].side_effect=RuntimeError('JOURNAL_CHANGED')
                with self.assertRaises(RuntimeError): resume.workflow('receipt', root, time.monotonic()+100)
                mocks['sql'].assert_not_called(); mocks['php_call'].assert_not_called()

    def test_deadline_and_final_drift_refuse_success(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            root,mocks=self.fixture(stack,directory)
            with self.assertRaisesRegex(RuntimeError,'OPERATION_DEADLINE'):resume.workflow('r',root,time.monotonic()-1)
            mocks['sql'].assert_not_called()
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            root,mocks=self.fixture(stack,directory);mocks['preservation'].side_effect=[{'hash':'same'},{}]
            with self.assertRaisesRegex(RuntimeError,'PRESERVATION_DRIFT'):resume.workflow('r',root,time.monotonic()+100)

    def test_scoped_guard_is_restored_even_after_failure(self):
        guard,workflow=resume.initial.validate_receipt,resume.initial.workflow
        with self.assertRaises(RuntimeError):
            with resume.operation_guard():
                self.assertIs(resume.initial.validate_receipt,resume.validate_receipt)
                self.assertIs(resume.initial.workflow,resume.workflow)
                raise RuntimeError('SYNTHETIC')
        self.assertIs(resume.initial.validate_receipt,guard);self.assertIs(resume.initial.workflow,workflow)

    def test_only_the_verified_stopped_state_can_be_resumed(self):
        state={'decision':'DECISION-038','status':'PARTIAL_JOURNAL_ONLY_VERIFIED_NO_RETRY',
               'local_table_names':['migrations'],'local_journal_rows':0,'grant_success_count':1,
               'archive_tables_created':0,'migration_executed':False,'import_executed':False,
               'activated':False,'test_restore_rows_unchanged':True,'runtime_witnesses_unchanged':True}
        with tempfile.TemporaryDirectory() as directory, patch.object(resume,'STOP',Path(directory)/'stop.json'):
            resume.STOP.write_text(json.dumps(state));self.assertEqual(state,resume.stopped_state())
            for key,value in [('local_journal_rows',1),('local_table_names',['migrations','archive_lots']),
                              ('grant_success_count',2),('archive_tables_created',1),('migration_executed',True),
                              ('test_restore_rows_unchanged',False),('decision','DECISION-037')]:
                wrong=dict(state);wrong[key]=value;resume.STOP.write_text(json.dumps(wrong))
                with self.assertRaisesRegex(RuntimeError,'STOP_STATE_REQUIRED'):resume.stopped_state()

    def test_receipt_needs_future_decision_resumption_and_artifacts(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            receipt,r,a=initial_tests.Op08Tests().receipt_fixture(stack,directory)
            for name in ('ROOT','QA','PLAN','AGREEMENT','ARTIFACTS'):
                stack.enter_context(patch.object(resume,name,getattr(resume.initial,name)))
            r['resume_from']='DECISION-038';a['resume_from']='DECISION-038'
            resume.initial.prior.REGISTRY.write_text('#### DECISION-999\n| Projet / périmètre | HOMYA OP-08 reprise |\n| Statut | VALIDÉ |\n')
            a['registry_after_sha256']=resume.digest(resume.initial.prior.REGISTRY)
            resume.AGREEMENT.write_text(json.dumps(a));r['agreement_sha256']=resume.digest(resume.AGREEMENT)
            receipt.write_text(json.dumps(r));self.assertEqual(r,resume.validate_receipt(receipt))
            for decision in ('DECISION-037','DECISION-038'):
                wrong=dict(r,decision=decision);receipt.write_text(json.dumps(wrong))
                with self.assertRaises(RuntimeError):resume.validate_receipt(receipt)
            wrong=dict(r,resume_from='DECISION-036');receipt.write_text(json.dumps(wrong))
            with self.assertRaises(RuntimeError):resume.validate_receipt(receipt)
            receipt.write_text(json.dumps(r));(resume.ROOT/'tool.py').write_text('changed')
            with self.assertRaisesRegex(RuntimeError,'ARTIFACT_CHANGED'):resume.validate_receipt(receipt)

    def test_unapproved_clis_refuse_before_database_or_access(self):
        commands=[['/usr/bin/python3',str(resume.ROOT/'scripts/op08_resume.py'),'--execute'],
                  ['/usr/bin/python3',str(resume.ROOT/'scripts/op08_resume.py'),'--validate-receipt','--receipt='+str(resume.QA/'op08-initial-execution-recu-038.json')],
                  [resume.initial.PHP,str(resume.ROOT/'scripts/op08_resume.php'),'--probe','--access=absent'],
                  [resume.initial.PHP,str(resume.ROOT/'scripts/op08_resume.php'),'--create-journal']]
        for command in commands:
            result=subprocess.run(command,capture_output=True,text=True,timeout=10)
            self.assertEqual(1,result.returncode);self.assertEqual('',result.stderr)
            self.assertEqual('STOP_INSPECT_NO_RETRY',json.loads(result.stdout)['status'])


if __name__=='__main__':unittest.main()
