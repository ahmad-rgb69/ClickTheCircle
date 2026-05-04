<?php
/** @var ?string $error */
include __DIR__ . '/header.php';
?>


<?php if (!empty($error)): ?>
    <p class="text-accentRed font-semibold w-full max-w-[960px]"><?= e($error) ?></p>
<?php endif; ?>

<!-- Wrapper Sentral -->
<div class="flex flex-col items-center justify-center min-h-[90vh] w-full px-4">
    <!-- 1. Window Bar (Slim & Wide) -->
    <div class="w-full max-w-[550px] bg-[#D9D9D9] border-2 border-black border-b-0 flex items-center px-2 py-0.5 gap-3">
        <div class="shrink-0 flex items-center justify-center py-1">
            <img src="img/logo.png" alt="Logo" class="h-6 w-auto object-contain [image-rendering:pixelated]">
        </div>
        <span class="font-sans text-[11px] text-gray-700 font-semibold italic truncate">ClickTheCircle Registration — New Player</span>
    </div>

    <!-- 2. Main Container -->
    <div class="w-full max-w-[550px] bg-[#9D9FA4] border-2 border-black p-5 pt-4 flex flex-col">
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-200 border-2 border-black p-2 mb-4 font-bold text-[11px] text-[#d83a3a]">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="post" enctype="multipart/form-data" class="flex flex-col">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <!-- Field Name -->
            <div class="border-2 border-black mb-3">
                <div class="bg-[#8D8D8D] border-b-2 border-black px-3 py-0.5 text-black font-bold text-[10px] uppercase">
                    Name :
                </div>
                <input type="text" name="nama" 
                       class="w-full bg-[#D1D1D1] px-3 py-2 outline-none text-black text-sm  placeholder:text-gray-500/50" 
                       placeholder="Enter your full name" required>
            </div>

            <!-- Field NPM (Password) -->
            <div class="border-2 border-black mb-3">
                <div class="bg-[#8D8D8D] border-b-2 border-black px-3 py-0.5 text-black font-bold text-[10px] uppercase">
                    NPM (Password) :
                </div>
                <input type="password" name="npm" 
                       class="w-full bg-[#D1D1D1] px-3 py-2 outline-none text-black text-sm  placeholder:text-gray-500/50" 
                       placeholder="example: 2410010000" required>
            </div>

            <!-- Field Photo (File) -->
            <div class="border-2 border-black mb-4">
                <div class="bg-[#8D8D8D] border-b-2 border-black px-3 py-0.5 text-black font-bold text-[10px] uppercase">
                    Foto Profil :
                </div>
                <input type="file" name="gambar" accept="image/*" 
                       class="w-full bg-[#D1D1D1] px-3 py-2 outline-none text-black text-xs cursor-pointer file:mr-4 file:py-1 file:px-4 file:border-0 file:text-xs file:font-bold file:bg-[#8D8D8D] file:text-black hover:file:bg-white" 
                       required>
            </div>

            <div class="flex justify-between items-center mb-6">
                <a href="login.php" class="text-black italic text-[10px] hover:underline">
                    Already have an account? Login here.
                </a>
            </div>

            <!-- Button Register -->
            <div class="flex justify-center">
                <button type="submit" 
                        class="w-full max-w-[200px] bg-[#D9D9D9] border-2 border-black py-2 font-bold text-lg text-black hover:bg-white hover:shadow-[4px_4px_0_0_rgba(0,0,0,1)] active:translate-y-[2px] active:shadow-none active:translate-y-[1px] transition-all">
                    REGISTER
                </button>
            </div>
        </form>
    </div>

    <div class="mt-2 text-[10px] text-black/40 font-mono italic">
        version 1.0.0
    </div>

</div>
<?php include __DIR__ . '/footer.php'; ?>
