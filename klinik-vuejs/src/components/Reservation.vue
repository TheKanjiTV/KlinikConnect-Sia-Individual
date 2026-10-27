<script setup>
import DatePicker from 'primevue/datepicker';
import { computed, ref } from "vue";
import { useAppointmentsReservasion } from '../stores/appointmentsReservasion';
import axios from 'axios';
import { toast } from 'vue-sonner';

const appointmentsReservasion = useAppointmentsReservasion()
const departments = computed(() => appointmentsReservasion.departments)
const doctors = computed(() => appointmentsReservasion.doctors)
const date = ref(new Date()); // Ensure this is correctly initialized
const departmentId = ref()
const fetchDoctors = () => {
    appointmentsReservasion.fetchDoctors(departmentId.value)
}
const doctorId = ref()
const showLoadingModal = ref(true)
const availableTimes = ref([])
const showTimesModal = ref(true)
const dateToShowInModal = ref()
const searchAvailability = async () => {
    const loadingModal = document.getElementById('loading-modal')
    loadingModal.showModal()
    const child = document.getElementById('child')
    child.innerHTML = '<span class="loading loading-spinner loading-lg"></span>'
    await new Promise(resolve => setTimeout(resolve, 1000));

    const localDate = new Date(date.value);
    localDate.setHours(0, 0, 0, 0);
    // Convert date to local format YYYY-MM-DD
    const formattedDate = localDate.toLocaleDateString('en-CA'); // 'en-CA' formats as YYYY-MM-DD
    dateToShowInModal.value = formattedDate


    await axios.post('/appointments-search-availability', {
        date: formattedDate,
        doctor: doctorId.value
    }).then(async (res) => {
        showLoadingModal.value = false
        await new Promise(resolve => setTimeout(resolve, 200));
        showLoadingModal.value = true
        availableTimes.value = res.data.data
        const timesModal = document.getElementById('times-modal')
        timesModal.showModal()
        doctorId.value = null
        departmentId.value = null
    }).catch(async (err) => {
        showLoadingModal.value = false
        toast.error(err.response.data.message)
        await new Promise(resolve => setTimeout(resolve, 200));
        showLoadingModal.value = true
    })
}


const reserveAppointment = async (startTime, date, doctorId, day) => {
    await axios.post('/appointments-register-appointment', {
        day: day,
        doctor: doctorId,
        startTime: startTime,
        date: date
    }).then((res) => {
        goToStripeCheckout(res.data.checkout_url)
    }).catch((err) => {
        toast.error(err.response.data.message)
    })
}

const goToStripeCheckout = (checkoutUrl) => {
    window.location.href = checkoutUrl
}


</script>

<template>

    <div class="flex justify-center px-4">
        <div class="w-[60%] max-[1536px]:w-[90%] max-[1024px]:w-full relative z-20 max-[1024px]:mt-10">
            <div id="bookNow-section"
                class="scroll-mt-24 bg-white dark:bg-slate-800 shadow-xl rounded-xl p-6 md:p-8 w-full grid gap-6 md:grid-cols-3 md:items-end">
                <div class="w-full flex flex-col items-center">
                    <label for="department-select"
                        class="font-recursive text-xl font-bold dark:text-gray-300">Select Department</label>
                    <select id="department-select" @change="fetchDoctors" v-model="departmentId"
                        class="select select-bordered w-full max-w-xs mt-3 bg-white dark:bg-slate-700 dark:text-gray-300">
                        <option value="" disabled selected>Select Department</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">
                            {{ department.name }}</option>
                    </select>
                </div>
                <div class="w-full flex flex-col items-center">
                    <label for="doctor-select"
                        class="font-recursive text-xl font-bold dark:text-gray-300">Choose Doctor</label>
                    <select id="doctor-select" v-model="doctorId"
                        class="select select-bordered w-full max-w-xs mt-3 bg-white dark:bg-slate-700 dark:text-gray-300">
                        <option disabled selected>Select a doctor</option>
                        <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">{{ doctor.name }} - ${{
                            doctor.appointment_price }}</option>
                    </select>
                </div>
                <div class="w-full flex flex-col sm:flex-row items-center sm:items-end sm:justify-center gap-3">
                    <div class="flex flex-col items-center sm:items-start">
                        <label class="font-recursive text-xl font-bold dark:text-gray-300">Select Date</label>
                        <DatePicker v-model="date" :pt="{
                            day: {
                                class: 'bg-primarycolor'
                            },
                        }" class="mt-3" inputClass="dark:bg-slate-700 dark:border-none rounded-xl dark:text-gray-300"
                            inputStyle="border-color:#cacbd1; background-color:#ffff;" />


                    </div>
                    <button @click="searchAvailability" type="submit"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 h-fit border-none text-sm font-medium text-white bg-primarycolor rounded-lg focus:ring-4 focus:outline-none focus:ring-blue-300">
                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                        </svg>
                        Search
                    </button>
                </div>
            </div>
        </div>
    </div>

    <dialog v-if="showLoadingModal" id="loading-modal" class="modal modal-middle text-primarycolor">
        <div class="modal-box bg-white rounded-lg shadow-lg p-6 flex justify-center" id="child">
        </div>
    </dialog>

    <dialog v-if="showTimesModal" id="times-modal" class="modal modal-middle">
        <div class="modal-box bg-white rounded-lg shadow-lg p-6 max-w-2xl">
            <h3 v-if="availableTimes.length" class="text-xl font-semibold text-gray-800 mb-4">Available Times</h3>
            <h3 v-else class="text-xl font-semibold text-gray-800 mb-4">No available times for this day right now!
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-for="time in availableTimes" :key="time.id"
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-100 p-3 rounded-lg">
                    <span class="text-gray-700">{{ `${time.start_time} - ${dateToShowInModal} (${time.day})`
                        }}</span>
                    <button @click="reserveAppointment(time.start_time, dateToShowInModal, time.doctor_id, time.day)"
                        class="bg-primarycolor hover:bg-blue-500 text-white py-1 px-3 rounded-lg transition duration-200">
                        Reserve
                    </button>
                </div>

            </div>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-sm">Close</button>
                </form>
            </div>
        </div>
    </dialog>
</template>