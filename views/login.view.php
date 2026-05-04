<?php
/** @var bool|string $error */
include __DIR__ . '/header.php';
?>
<!-- Hehe baiki bagian ini biar bagus tampilannya, paling kd mirip lwn di bagian meisi nama lwn pw, di register jua-->
<?php if (!empty($error)): ?>
    <div class="z-[100px] fixed border-2 bg-[#D9D9D9] border-black border-b-0 items-center px-2 py-0.5 gap-3>
        <p class="text-red-500 font-black w-full max-w-[960px]">Name or NPM is not registered.</p>
    </div>
    
<?php endif; ?>




<div class="flex flex-col items-center justify-center min-h-[90vh] w-full px-4">
    

    <div class="w-full max-w-[550px] bg-[#D9D9D9] border-2 border-black border-b-0 flex items-center px-2 py-0 gap-3">
        <div class="shrink-0 flex items-center justify-center py-1">
            <img src="img/logo.png" alt="Logo" class="h-6 w-auto object-contain [image-rendering:pixelated]">
        </div>
        <span class="font-sans text-[11px] text-gray-700 font-semibold italic truncate">ClickTheCircle Beta v1.0.0.</span>
    </div>

    <div class="w-full max-w-[550px] bg-[#9D9FA4] border-2 border-black p-5 pt-4 flex flex-col">
        
        <form action="login.php" method="post" class="flex flex-col">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div class="border-2 border-black mb-3">
                <div class="bg-[#8D8D8D] border-b-2 border-black px-3 py-0.5 text-black font-bold text-[11px] uppercase">
                    Name :
                </div>
                <input type="text" name="nama" 
                       class="w-full bg-[#D1D1D1] px-3 py-2 outline-none text-black text-sm  placeholder:text-gray-500/50" 
                       placeholder="Insert name here" required>
            </div>

            <div class="border-2 border-black mb-2">
                <div class="bg-[#8D8D8D] border-b-2 border-black px-3 py-0.5 text-black font-bold text-[11px] uppercase">
                    NPM (Password) :
                </div>
                <input type="password" name="npm" 
                       class="w-full bg-[#D1D1D1] px-3 py-2 outline-none text-black text-sm  placeholder:text-gray-500/50" 
                       placeholder="example:2410010000" required>
            </div>

            <div class="flex justify-between items-center mb-6">
                <a href="register.php" class="text-black italic text-[10px] hover:underline">
                    Belum punya akun? Register disini.
                </a>
            </div>
            
            <div class="flex justify-center">
                <button type="submit" 
                        class="w-full max-w-[200px] bg-[#D9D9D9] border-2 border-black py-2 font-bold text-lg text-black hover:shadow-[4px_4px_0_0_rgba(0,0,0,1)] hover:bg-white active:translate-y-[2px] active:shadow-none active:translate-y-[1px] transition-all duration-200">
                    LOG IN
                </button>
            </div>
        </form>
    </div>

    <div class="mt-2 text-[10px] text-black/40 font-mono italic">
        version 1.0.0
    </div>

</div>
<?php include __DIR__ . '/footer.php'; ?>
