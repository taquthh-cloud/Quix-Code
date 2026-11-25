<?php

use App\Exceptions\UnsafeUploadException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Support\SecureUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

function secureUploadPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAFUlEQVR42mM8ISfHgA0wMeAAg1MCANDKARQfGKMRAAAAAElFTkSuQmCC');
}

function secureUploadJpeg(): string
{
    return base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAAIAAgDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDgKKKK+eP2E//Z');
}

function secureUploadPdf(string $extra = ''): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog {$extra}>>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

/** Menjalankan aksi dan mengembalikan alasan penolakan, atau null bila lolos. */
function secureUploadReason(Closure $action): ?string
{
    try {
        $action();
    } catch (UnsafeUploadException $e) {
        return $e->reason;
    }

    return null;
}

function secureUploadFile(string $name, string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

beforeEach(function () {
    app()->setLocale('id');
    Storage::fake('local');
    Storage::fake('public');
});

it('menyimpan gambar asli dengan nama ULID di disk privat tanpa URL publik', function () {
    $stored = SecureUpload::store(secureUploadFile('bukti-transfer-saya.png', secureUploadPng()), 'payments');

    expect($stored->path)->toMatch('#^payments/[0-9a-z]{26}\.png$#D')
        ->and($stored->mime)->toBe('image/png')
        ->and($stored->disk)->toBe('local')
        ->and($stored->size)->toBeGreaterThan(0)
        ->and($stored->path)->not->toContain('bukti-transfer');

    Storage::disk('local')->assertExists($stored->path);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();

    $info = getimagesizefromstring(Storage::disk('local')->get($stored->path));
    expect($info['mime'])->toBe('image/png');
});

it('menerima JPEG (ekstensi jpeg disimpan sebagai jpg) dan PDF bersih untuk bukti bayar', function () {
    $jpeg = SecureUpload::store(secureUploadFile('foto.jpeg', secureUploadJpeg()), 'payments');
    $pdf = SecureUpload::store(secureUploadFile('bukti.pdf', secureUploadPdf()), 'payments');

    expect($jpeg->path)->toEndWith('.jpg')->and($jpeg->mime)->toBe('image/jpeg')
        ->and($pdf->path)->toEndWith('.pdf')->and($pdf->mime)->toBe('application/pdf');
});

it('menolak skrip yang menyamar sebagai gambar, walau header dari browser mengaku image', function () {
    $script = secureUploadFile('bukti.png', '<?php echo shell_exec($_GET["c"]);');

    expect(secureUploadReason(fn () => SecureUpload::store($script, 'payments')))->toBe('type');
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('menolak ekstensi palsu: isi PNG tetapi bernama .php, atau isi PNG bernama .jpg', function () {
    expect(secureUploadReason(fn () => SecureUpload::store(secureUploadFile('shell.php', secureUploadPng()), 'payments')))
        ->toBe('extension');
    expect(secureUploadReason(fn () => SecureUpload::store(secureUploadFile('foto.jpg', secureUploadPng()), 'payments')))
        ->toBe('extension');
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('menolak file kosong dan file yang melebihi batas ukuran', function () {
    expect(secureUploadReason(fn () => SecureUpload::inspect(secureUploadFile('a.png', ''))))->toBe('empty');

    config(['uploads.max_kb.proof' => 1]);
    $big = secureUploadFile('a.png', secureUploadPng().str_repeat('A', 2048));

    expect(secureUploadReason(fn () => SecureUpload::inspect($big)))->toBe('too_large');
});

it('menolak polyglot: gambar valid dengan kode PHP atau skrip di belakangnya', function () {
    $php = secureUploadFile('a.png', secureUploadPng().'<?php system($_GET["c"]); ?>');
    $js = secureUploadFile('b.png', secureUploadPng().'<SCRIPT>alert(1)</SCRIPT>');

    expect(secureUploadReason(fn () => SecureUpload::inspect($php)))->toBe('embedded_code');
    expect(secureUploadReason(fn () => SecureUpload::inspect($js)))->toBe('embedded_code');
});

it('menolak SVG dan HTML karena bisa memuat skrip', function () {
    $svg = secureUploadFile('a.svg', '<svg xmlns="http://www.w3.org/2000/svg"><circle r="5"/></svg>');
    $html = secureUploadFile('a.html', '<html><body>halo</body></html>');

    expect(secureUploadReason(fn () => SecureUpload::inspect($svg)))->toBe('type');
    expect(secureUploadReason(fn () => SecureUpload::inspect($html)))->toBe('type');
});

it('menolak PDF dengan konten aktif atau sampah di depan header', function () {
    $active = secureUploadFile('a.pdf', secureUploadPdf('/OpenAction << /S /JavaScript /JS (app.alert(1)) >> '));
    $junk = secureUploadFile('b.pdf', "GARBAGE\n".secureUploadPdf());

    expect(secureUploadReason(fn () => SecureUpload::inspect($active)))->toBe('active_content');
    expect(secureUploadReason(fn () => SecureUpload::inspect($junk)))->not->toBeNull();
});

it('jenis image (tangkapan layar tiket) menolak PDF', function () {
    $pdf = secureUploadFile('a.pdf', secureUploadPdf());

    expect(secureUploadReason(fn () => SecureUpload::inspect($pdf, SecureUpload::KIND_IMAGE)))->toBe('type');
    expect(SecureUpload::inspect(secureUploadFile('a.png', secureUploadPng()), SecureUpload::KIND_IMAGE))->toBe('image/png');
});

it('membuang EXIF dan data tambahan dari JPEG yang disimpan', function () {
    $payload = "Exif\0\0RAHASIA-GPS-LOKASI";
    $jpeg = secureUploadJpeg();
    $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', 2 + strlen($payload)).$payload.substr($jpeg, 2);

    expect($withExif)->toContain('RAHASIA-GPS-LOKASI');

    $stored = SecureUpload::store(secureUploadFile('foto.jpg', $withExif), 'payments');
    $bytes = Storage::disk('local')->get($stored->path);

    expect($bytes)->not->toContain('RAHASIA-GPS-LOKASI')
        ->and($bytes)->not->toContain('Exif')
        ->and(getimagesizefromstring($bytes)['mime'])->toBe('image/jpeg');
})->skip(! extension_loaded('gd'), 'Butuh ekstensi GD.');

it('menolak direktori tujuan yang tidak aman', function () {
    expect(fn () => SecureUpload::store(secureUploadFile('a.png', secureUploadPng()), '../public'))
        ->toThrow(InvalidArgumentException::class);
});

it('aturan SafeUpload menolak file berbahaya dengan pesan sesuai bahasa', function () {
    $ok = Validator::make(['f' => secureUploadFile('a.png', secureUploadPng())], ['f' => [new SafeUpload('proof')]]);
    $bad = Validator::make(['f' => secureUploadFile('a.png', '<?php echo 1;')], ['f' => [new SafeUpload('proof')]]);

    expect($ok->fails())->toBeFalse()
        ->and($bad->fails())->toBeTrue()
        ->and($bad->errors()->first('f'))->toBe(__('uploads.errors.type'));

    app()->setLocale('en');
    $badEn = Validator::make(['f' => secureUploadFile('a.png', '<?php echo 1;')], ['f' => [new SafeUpload('proof')]]);

    expect($badEn->errors()->first('f'))->toBe(__('uploads.errors.type'))
        ->and($badEn->errors()->first('f'))->toContain('not allowed');
});

it('ClamAV opsional: terinfeksi atau pemindai bermasalah ditolak, bersih lolos', function () {
    config(['uploads.clamav.enabled' => true]);

    Process::fake(['*' => Process::result('FOUND', '', 1)]);
    expect(secureUploadReason(fn () => SecureUpload::store(secureUploadFile('a.png', secureUploadPng()), 'payments')))->toBe('malware');

    Process::fake(['*' => Process::result('', 'error', 2)]);
    expect(secureUploadReason(fn () => SecureUpload::store(secureUploadFile('a.png', secureUploadPng()), 'payments')))->toBe('scan_failed');

    Process::fake(['*' => Process::result('OK', '', 0)]);
    $stored = SecureUpload::store(secureUploadFile('a.png', secureUploadPng()), 'payments');

    Storage::disk('local')->assertExists($stored->path);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});

it('file hasil unggahan hanya bisa diunduh pemilik dan admin, dengan nosniff', function () {
    $owner = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $admin = User::factory()->admin()->withTwoFactor()->create();
    $project = Project::factory()->for($owner, 'client')->create();
    $invoice = Invoice::factory()->for($project)->create();
    $stored = SecureUpload::store(secureUploadFile('bukti.png', secureUploadPng()), 'payments');
    $payment = Payment::factory()->for($invoice)->create(['proof_path' => $stored->path]);

    $this->actingAs($owner)
        ->get(route('admin.payments.proof', ['payment' => $payment->id]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($admin)
        ->get(route('admin.payments.proof', ['payment' => $payment->id]))
        ->assertOk();

    $this->actingAs($other)
        ->get(route('admin.payments.proof', ['payment' => $payment->id]))
        ->assertNotFound();

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('semua teks upload tersedia dalam dua bahasa dengan kunci yang sama', function () {
    $id = require lang_path('id/uploads.php');
    $en = require lang_path('en/uploads.php');

    expect(array_keys($id['errors']))->toBe(array_keys($en['errors']));
});
