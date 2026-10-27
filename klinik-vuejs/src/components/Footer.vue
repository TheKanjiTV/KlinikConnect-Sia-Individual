<script setup>
import { RouterLink } from 'vue-router';
</script>


<template>
    <footer class="bg-[#4A90E2] dark:bg-darkmodebg">
        <div
            class="mx-auto w-[60%] max-[1536px]:w-[90%] max-[1024px]:w-[90%] pt-20 pb-10 flex flex-col gap-12 max-[1024px]:text-center">
            <div class="grid gap-12 lg:grid-cols-4 md:grid-cols-2">
                <div>
                    <h1 class="font-recursive text-4xl text-white font-bold">Klinik</h1>
                    <p class="font-recursive text-blue-100 mt-5 leading-relaxed">
                        Compassionate, modern healthcare for you and your family.
                    </p>
                </div>
                <div class="flex flex-col gap-4">
                    <h2 class="font-recursive text-gray-200 text-xl">Menu</h2>
                    <RouterLink to="/" class="font-recursive font-semibold text-white dark:text-gray-400 hover:text-[#FAD149] transition-colors">Home</RouterLink>
                    <RouterLink to="/about" class="font-recursive font-semibold text-white dark:text-gray-400 hover:text-[#FAD149] transition-colors">About</RouterLink>
                    <RouterLink to="/our-offers" class="font-recursive font-semibold text-white dark:text-gray-400 hover:text-[#FAD149] transition-colors">Offers</RouterLink>
                </div>

                <div class="flex flex-col gap-4">
                    <h2 class="font-recursive text-gray-200 text-xl">Services</h2>
                    <RouterLink to="/our-offers"
                        class="font-recursive font-semibold text-white dark:text-gray-400 hover:text-[#FAD149] transition-colors">
                        Health Promotion</RouterLink>
                    <RouterLink to="/reserve-appointment"
                        class="font-recursive font-semibold text-white dark:text-gray-400 hover:text-[#FAD149] transition-colors">
                        Reserve Appointment</RouterLink>
                </div>

                <div class="flex flex-col gap-4 max-[1024px]:items-center">
                    <h2 class="font-recursive text-gray-200 text-xl">Follow Us</h2>
                    <div class="flex gap-4">
                        <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer"
                            aria-label="Facebook"
                            class="h-12 w-12 rounded-full bg-[#FAD149] flex justify-center items-center hover:brightness-95 transition">
                            <svg width="26" height="26" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                <path fill="#584400" d="M19.254 2C15.312 2 13 4.082 13 8.826V13H8v5h5v12h5V18h4l1-5h-5V9.672C18 7.885 18.583 7 20.26 7H23V2.205C22.526 2.141 21.145 2 19.254 2" />
                            </svg>
                        </a>

                        <a href="https://x.com/" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)"
                            class="h-12 w-12 rounded-full bg-[#FAD149] flex justify-center items-center hover:brightness-95 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 32 32">
                                <path fill="#584400" d="M28 8.559a9.813 9.813 0 0 1-2.828.773a4.94 4.94 0 0 0 2.164-2.723a9.92 9.92 0 0 1-3.125 1.196a4.924 4.924 0 0 0-8.52 3.367c0 .387.043.762.13 1.121A13.957 13.957 0 0 1 5.67 7.148a4.885 4.885 0 0 0-.667 2.477c0 1.707.867 3.215 2.191 4.098a4.895 4.895 0 0 1-2.23-.618v.063a4.922 4.922 0 0 0 3.95 4.828a4.902 4.902 0 0 1-2.224.086a4.932 4.932 0 0 0 4.598 3.422A9.875 9.875 0 0 1 4 23.539a13.924 13.924 0 0 0 7.547 2.215c9.058 0 14.012-7.504 14.012-14.012c0-.21-.008-.426-.016-.637A10.085 10.085 0 0 0 28 8.56z" />
                            </svg>
                        </a>

                        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer"
                            aria-label="Instagram"
                            class="h-12 w-12 rounded-full bg-[#FAD149] flex justify-center items-center hover:brightness-95 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 14 14">
                                <g fill="none" stroke="#584400" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.333 3.644a.25.25 0 1 1 0-.5m0 .5a.25.25 0 1 0 0-.5" />
                                    <path d="M.858 3.431A2.573 2.573 0 0 1 3.431.858h6.862a2.573 2.573 0 0 1 2.573 2.573v6.862a2.573 2.573 0 0 1-2.573 2.573H3.43a2.573 2.573 0 0 1-2.573-2.573V3.43Z" />
                                    <path d="M4.312 6.862a2.55 2.55 0 1 0 5.1 0a2.55 2.55 0 1 0-5.1 0" />
                                </g>
                            </svg>
                        </a>
                    </div>
                    <a href="mailto:ddrboom4@gmail.com"
                        class="font-recursive font-semibold text-white dark:text-gray-400 hover:text-[#FAD149] transition-colors">
                        ddrboom4@gmail.com
                    </a>
                </div>
            </div>

            <div
                class="border-t border-white/25 pt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-sm">
                <p class="font-recursive text-blue-100 dark:text-gray-400">© 2026 Klinik. All rights reserved.</p>
                <p class="font-recursive text-blue-100 dark:text-gray-400">
                    Developer Email:
                    <a href="mailto:ddrboom4@gmail.com" class="font-semibold text-white hover:text-[#FAD149]">ddrboom4@gmail.com</a>
                </p>
            </div>
        </div>
    </footer>
</template>
