<?php

namespace App\Services\Admin;

use App\Services\Commerce\OrderStore;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class Messages
{
    public function create(array $customer, string $body, string $nonce): array
    {
        $db = app(OrderStore::class)->connection();
        $id = (string) Str::uuid();
        $code = Str::random(32);
        $db->transaction(function () use ($db, $id, $code, $customer, $body, $nonce) {
            $db->table('commerce_admin_threads')->insert(['id' => $id, 'customer_encrypted' => Crypt::encryptString(json_encode($customer, JSON_THROW_ON_ERROR)), 'access_hash' => hash('sha256', $code), 'unread' => true, 'created_at' => now(), 'updated_at' => now()]);
            $this->append($db, $id, 'client', $body, $nonce);
        });

        return ['id' => $id, 'code' => $code];
    }

    public function authorize(string $id, string $code): bool
    {
        $row = app(OrderStore::class)->connection()->table('commerce_admin_threads')->where('id', $id)->first(['access_hash']);

        return $row !== null && hash_equals($row->access_hash, hash('sha256', $code));
    }

    public function thread(string $id): array
    {
        $db = app(OrderStore::class)->connection();
        $thread = $db->table('commerce_admin_threads')->where('id', $id)->first();
        abort_unless($thread, 404);
        $messages = $db->table('commerce_admin_messages')->where('thread_id', $id)->orderBy('sequence')->get()->map(function ($row) {
            $row->body = Crypt::decryptString($row->body_encrypted);

            return $row;
        });

        return ['thread' => $thread, 'customer' => json_decode(Crypt::decryptString($thread->customer_encrypted), true, 16, JSON_THROW_ON_ERROR), 'messages' => $messages];
    }

    public function inbox()
    {
        return app(OrderStore::class)->connection()->table('commerce_admin_threads')->orderByDesc('unread')->orderByDesc('updated_at')->paginate(20)->through(function ($thread) {
            $thread->customer = json_decode(Crypt::decryptString($thread->customer_encrypted), true, 16, JSON_THROW_ON_ERROR);

            return $thread;
        });
    }

    public function reply(string $id, string $body, string $nonce, bool $admin, bool $email = false): bool
    {
        $db = app(OrderStore::class)->connection();
        $created = $db->transaction(function () use ($db, $id, $body, $nonce, $admin) {
            abort_unless($db->table('commerce_admin_threads')->where('id', $id)->lockForUpdate()->first(), 404);
            if ($db->table('commerce_admin_messages')->where('nonce', $nonce)->exists()) {
                return false;
            }
            $this->append($db, $id, $admin ? 'admin' : 'client', $body, $nonce);
            $db->table('commerce_admin_threads')->where('id', $id)->update(['unread' => ! $admin, 'updated_at' => now()]);

            return true;
        });
        if ($created && $admin && $email) {
            $customer = $this->thread($id)['customer'];
            $state = 'failed';
            if ($this->emailReady() && filter_var($customer['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                // Persist the attempt before handing off to SMTP; a repeated form nonce never sends again.
                $db->table('commerce_admin_messages')->where('nonce', $nonce)->update(['email_status' => 'attempting']);
                try {
                    Mail::raw('HOMYA — Votre dossier '.$id."\n\n".$body, fn ($message) => $message->to($customer['email'])->subject('HOMYA — Réponse à votre message'));
                    $state = 'submitted'; // accepted by the transport, not proof of mailbox delivery
                } catch (\Throwable) {
                    $state = 'unknown';
                }
            }
            $db->table('commerce_admin_messages')->where('nonce', $nonce)->update(['email_status' => $state]);
        }

        return $created;
    }

    public function emailReady(): bool
    {
        return config('mail.default') === 'smtp' && is_string(config('mail.mailers.smtp.host'))
            && config('mail.mailers.smtp.host') !== '' && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && config('mail.from.address') === config('contact.email');
    }

    private function append($db, string $id, string $author, string $body, string $nonce): void
    {
        $messageId = (string) Str::uuid();
        $db->table('commerce_admin_messages')->insert(['id' => $messageId, 'thread_id' => $id, 'sequence' => ((int) $db->table('commerce_admin_messages')->where('thread_id', $id)->max('sequence')) + 1, 'author' => $author, 'body_encrypted' => Crypt::encryptString($body), 'nonce' => $nonce, 'email_status' => 'not_requested', 'created_at' => now()]);
        app(Activity::class)->record('message_'.$author, $id, $db, $messageId);
    }
}
