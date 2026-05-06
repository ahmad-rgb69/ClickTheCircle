<?php
/** @var bool|string $error */
include __DIR__ . '/header.php';
?>
<h1 class="h-title">Login</h1>

<?php if (!empty($error)): ?>
    <p class="text-accentRed font-semibold w-full max-w-[960px]">Name or NPM is not registered.</p>
<?php endif; ?>

<form action="login.php" method="post" class="w-full max-w-[960px]">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <ul class="panel-box list-none p-4 m-0 flex flex-col gap-3">
        <li><label class="block font-semibold">Name: <input type="text" name="nama" required class="field"></label></li>
        <li><label class="block font-semibold">NPM:  <input type="password" name="npm"  required class="field"></label></li>
        <li><button type="submit" class="btn">Sign In</button></li>
    </ul>
</form>
<p class="w-full max-w-[960px] mt-3"><a href="register.php" class="underline underline-offset-4 hover:text-accentRed">Don't have an account? Register here</a></p>
<?php include __DIR__ . '/footer.php'; ?>
