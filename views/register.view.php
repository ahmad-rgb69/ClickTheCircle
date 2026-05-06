<?php
/** @var ?string $error */
include __DIR__ . '/header.php';
?>
<h1 class="h-title">Create New Account</h1>

<?php if (!empty($error)): ?>
    <p class="text-accentRed font-semibold w-full max-w-[960px]"><?= e($error) ?></p>
<?php endif; ?>

<form action="register.php" method="post" enctype="multipart/form-data" class="w-full max-w-[960px]">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <ul class="panel-box list-none p-4 m-0 flex flex-col gap-3">
        <li><label class="block font-semibold">Name:  <input type="text" name="nama" required class="field"></label></li>
        <li><label class="block font-semibold">NPM:   <input type="password" name="npm"  required class="field"></label></li>
        <li><label class="block font-semibold">Photo: <input type="file" name="gambar" accept="image/*" required class="field cursor-pointer"></label></li>
        <li><button type="submit" class="btn">Register</button></li>
    </ul>
</form>
<p class="w-full max-w-[960px] mt-3"><a href="login.php" class="underline underline-offset-4 hover:text-accentRed">Already have an account? Login</a></p>
<?php include __DIR__ . '/footer.php'; ?>
