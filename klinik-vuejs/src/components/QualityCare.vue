<script setup>

</script>



<template>
    <div class="flex justify-center flex-col items-center dark:bg-darkmodebg bg-bgcolor pb-20">
        <div v-motion-slide-visible-top
            class="w-[60%] max-[1536px]:w-[90%] max-[1024px]:w-[80%] flex flex-col justify-center items-center text-center mt-[120px]">
            <h3 class="font-recursive text-[#A1A7B0] tracking-widest">Quality Care</h3>
            <h1 class="font-recursive dark:text-white font-bold text-6xl max-[1024px]:text-4xl text-[#333333] mt-7">
                Comprehensive Healthcare Solutions</h1>
        </div>


        <div v-motion-slide-visible-top
            class="w-[60%] max-[1536px]:w-[90%] max-[1024px]:w-[80%] grid gap-8 md:grid-cols-2 mt-14">
            <div class="overflow-hidden rounded-xl shadow-md hover:shadow-xl transition-shadow bg-white dark:bg-primarycardsbg">
                <div class="bg-bgcolor dark:bg-primarycardsbg h-56 flex items-center justify-center p-6">
                    <img src="/src/assets/images/health-care-solutions-image1.svg" alt="">
                </div>
                <div class="flex flex-col justify-center max-[1024px]:text-center p-6">
                    <h2 class="font-recursive dark:text-white text-[#333333] tracking-tight text-2xl font-bold">Primary Care</h2>
                    <p class="font-recursive text-gray-400 tracking-wide mt-2">Primary healthcare excellence</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl shadow-md hover:shadow-xl transition-shadow bg-white dark:bg-primarycardsbg">
                <div class="bg-bgcolor dark:bg-primarycardsbg h-56 flex items-center justify-center p-6">
                    <img src="/src/assets/images/health-care-solutions-image2.svg" alt="">
                </div>
                <div class="flex flex-col justify-center max-[1024px]:text-center p-6">
                    <h2 class="font-recursive dark:text-white text-[#333333] tracking-tight text-2xl font-bold">Diagnostics</h2>
                    <p class="font-recursive text-gray-400 tracking-wide mt-2">Advanced Diagnostic Services</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl shadow-md hover:shadow-xl transition-shadow bg-white dark:bg-primarycardsbg">
                <div class="bg-bgcolor dark:bg-primarycardsbg h-56 flex items-center justify-center p-6">
                    <img src="/src/assets/images/health-care-solutions-image3.svg" alt="">
                </div>
                <div class="flex flex-col justify-center max-[1024px]:text-center p-6">
                    <h2 class="font-recursive dark:text-white text-[#333333] tracking-tight text-2xl font-bold">Surgery</h2>
                    <p class="font-recursive text-gray-400 tracking-wide mt-2">Surgical expertise offered</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl shadow-md hover:shadow-xl transition-shadow bg-white dark:bg-primarycardsbg">
                <div class="bg-bgcolor dark:bg-primarycardsbg h-56 flex items-center justify-center p-6">
                    <img src="/src/assets/images/health-care-solutions-image4.svg" alt="">
                </div>
                <div class="flex flex-col justify-center max-[1024px]:text-center p-6">
                    <h2 class="font-recursive dark:text-white text-[#333333] tracking-tight text-2xl font-bold">Cosmetic Care</h2>
                    <p class="font-recursive text-gray-400 tracking-wide mt-2">Cutting-edge cosmetic treatments</p>
                </div>
            </div>
        </div>
    </div>
</template>
