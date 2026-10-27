<script setup>

</script>


<template>


<div class="flex justify-center flex-col items-center">
    <div v-motion-slide-visible-top class="w-[60%] mt-20 max-[1536px]:w-[90%] max-[1024px]:w-[80%] max-[1024px]:text-center">
        <h3 class="font-recursive text-[#A1A7B0] tracking-widest">Hear From Our Patients </h3>
        <h1 class="font-recursive font-bold text-6xl max-[1024px]:text-4xl text-[#333333] mt-7 dark:text-white">Testimonials</h1>

        <div class="grid gap-8 md:grid-cols-3 mt-10">
            <div class="bg-white dark:bg-primarycardsbg shadow-lg rounded-xl p-8 flex flex-col justify-between gap-10">
                <div>
                    <p class="font-recursive dark:text-gray-300 text-[#333333] leading-relaxed text-lg">
                        Modern Health Clinic has transformed my life. Their commitment to quality care is unmatched in the industry.
                    </p>
                </div>
                <div class="flex max-[1024px]:justify-center items-center gap-4 mt-2">
                    <div class="h-16 w-16 rounded-full border border-gray-300 overflow-hidden shrink-0">
                        <img src="/src/assets/images/testimonials-image1.jpg" alt="Jessica Brown" class="h-full w-full object-cover">
                    </div>
                    <div>
                        <h2 class="font-recursive text-[#333333] text-lg dark:text-gray-400">Jessica Brown</h2>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-primarycardsbg shadow-lg rounded-xl p-8 flex flex-col justify-between gap-10">
                <div>
                    <p class="font-recursive dark:text-gray-300 text-[#333333] leading-relaxed text-lg">
                        Thanks to the Modern Health Clinic, I finally feel heard and cared for. Exceptional service!
                    </p>
                </div>
                <div class="flex max-[1024px]:justify-center items-center gap-4 mt-2">
                    <div class="h-16 w-16 rounded-full border border-gray-300 overflow-hidden shrink-0">
                        <img src="/src/assets/images/testimonials-image2.jpg" alt="David Johnson" class="h-full w-full object-cover">
                    </div>
                    <div>
                        <h2 class="font-recursive text-[#333333] text-lg dark:text-gray-400">David Johnson</h2>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-primarycardsbg shadow-lg rounded-xl p-8 flex flex-col justify-between gap-10">
                <div>
                    <p class="font-recursive dark:text-gray-300 text-[#333333] leading-relaxed text-lg">
                        The best decision I made was visiting Modern Health Clinic. Top-notch care!
                    </p>
                </div>
                <div class="flex max-[1024px]:justify-center items-center gap-4 mt-2">
                    <div class="h-16 w-16 rounded-full border border-gray-300 overflow-hidden shrink-0">
                        <img src="/src/assets/images/testimonials-image3.jpg" alt="Karen Thomas" class="h-full w-full object-cover">
                    </div>
                    <div>
                        <h2 class="font-recursive text-[#333333] text-lg dark:text-gray-400">Karen Thomas</h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</template>