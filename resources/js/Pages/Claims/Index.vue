<script setup>
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { useForm, usePage } from "@inertiajs/vue3";
import {  watch } from "vue";

const user = usePage().props.auth.user;
const specialties = usePage().props.specialties || [
    { id: 1, name: "Cardiology", code: "CARD" },
    { id: 2, name: "Orthopedics", code: "ORTH" },
    { id: 3, name: "Neurology", code: "NEUR" },
    { id: 4, name: "Oncology", code: "ONCO" },
    { id: 5, name: "Pediatrics", code: "PEDI" },
    { id: 6, name: "Dermatology", code: "DERM" },
    { id: 7, name: "Gastroenterology", code: "GAST" },
];


const form = useForm({
    insurer_code: "",
    provider_name: user.provider?.name || "",
    encounter_date: new Date().toISOString().substr(0, 10),
    specialty_id: "",
    priority_level: 3, 
    items: [{ name: "", unit_price: "", quantity: 1, subtotal: 0 }],
    total_amount: 0,
});



const addItem = () => {
    form.items.push({ name: "", unit_price: "", quantity: 1, subtotal: 0 });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
        calculateTotals();
    }
};

const calculateSubtotal = (item) => {
    const price = parseFloat(item.unit_price) || 0;
    const qty = parseInt(item.quantity) || 0;
    item.subtotal = price * qty;
    return item.subtotal.toFixed(2);
};

const calculateTotals = () => {
    form.total_amount = form.items.reduce((sum, item) => {
        return sum + (parseFloat(item.subtotal) || 0);
    }, 0);
};


watch(
    () => form.items,
    () => {
        calculateTotals();
    },
    { deep: true }
);

const submitClaim = () => {
    console.log(form);  
    form.items.forEach((item) => calculateSubtotal(item));
    calculateTotals();

    form.post(route("claims.store"), {
        onSuccess: () => {
            form.reset();
            form.items = [
                { name: "", unit_price: "", quantity: 1, subtotal: 0 },
            ];
        },
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "NGN",
        minimumFractionDigits: 2,
    }).format(value);
};
</script>

<template>
    <Head title="Submit Claim" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Submit Medical Claim
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="form.post(route('claims.store'))" class="space-y-8">
                            <!-- Basic Claim Information -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <InputLabel
                                        for="insurer_code"
                                        value="Insurer Code"
                                    />
                                    <TextInput
                                        id="insurer_code"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.insurer_code"
                                        required
                                        placeholder="e.g. HFI"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.insurer_code"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        for="provider_name"
                                        value="Provider Name"
                                    />
                                    <TextInput
                                        id="provider_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.provider_name"
                                        required
                                        placeholder="Your healthcare facility name"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.provider_name"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        for="encounter_date"
                                        value="Encounter Date"
                                    />
                                    <TextInput
                                        id="encounter_date"
                                        type="date"
                                        class="mt-1 block w-full"
                                        v-model="form.encounter_date"
                                        required
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.encounter_date"
                                    />
                                </div>
                            </div>

                            <!-- Specialty and Priority -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 " >
                                <div>
                                    <InputLabel
                                        for="specialty_id"
                                        value="Medical Specialty"
                                    />
                                    <select
                                        id="specialty_id"
                                        v-model="form.specialty_id"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="" disabled>
                                            Select a specialty
                                        </option>
                                        <option
                                            v-for="specialty in specialties"
                                            :key="specialty.id"
                                            :value="specialty.id"
                                        >
                                            {{ specialty.name }} ({{
                                                specialty.code
                                            }})
                                        </option>
                                    </select>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.specialty_id"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        for="priority_level"
                                        value="Priority Level"
                                    />
                                    <div class="mt-1">
                                        <div
                                            class="flex items-center gap-6"
                                        >
                                            <div
                                                v-for="level in 5"
                                                :key="level"
                                                class="flex items-center space-x-2 mr-6"
                                            >
                                                <input
                                                    type="radio"
                                                    :id="`priority_${level}`"
                                                    :value="level"
                                                    v-model="
                                                        form.priority_level
                                                    "
                                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 mr-6"
                                                />
                                                <label
                                                    :for="`priority_${level}`"
                                                    class="ml-2 block text-sm text-gray-700"
                                                >
                                                    {{ level }}
                                                    <span
                                                        class="text-xs text-gray-500"
                                                    >
                                                        {{
                                                            
                                                        }}
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.priority_level"
                                    />
                                </div>
                            </div>

                           
                            <div class="mt-22">
                                <h3
                                    class="text-lg font-medium text-gray-900 border-b pb-2"
                                >
                                    Claim Items
                                </h3>

                                <div class="overflow-x-auto mt-4">
                                    <table
                                        class="min-w-full divide-y divide-gray-200"
                                    >
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                                >
                                                    Item
                                                </th>
                                                <th
                                                    scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                                >
                                                    Unit Price
                                                </th>
                                                <th
                                                    scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                                >
                                                    Quantity
                                                </th>
                                                <th
                                                    scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                                >
                                                    Subtotal
                                                </th>
                                                <th
                                                    scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                                >
                                                    Action
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="bg-white divide-y divide-gray-200"
                                        >
                                            <tr
                                                v-for="(
                                                    item, index
                                                ) in form.items"
                                                :key="index"
                                                class="hover:bg-gray-50"
                                            >
                                                <td class="px-6 py-4">
                                                    <TextInput
                                                        type="text"
                                                        class="w-full"
                                                        v-model="item.name"
                                                        required
                                                        placeholder="Item description"
                                                    />
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="relative">
                                                        <div
                                                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                                                        >
                                                         
                                                        </div>
                                                        <TextInput
                                                            type="number"
                                                            class="pl-7 w-full"
                                                            step="0.01"
                                                            min="0"
                                                            v-model="
                                                                item.unit_price
                                                            "
                                                            required
                                                            placeholder="0.00"
                                                            @input="
                                                                calculateSubtotal(
                                                                    item
                                                                )
                                                            "
                                                        />
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <TextInput
                                                        type="number"
                                                        class="w-full"
                                                        min="1"
                                                        v-model="item.quantity"
                                                        required
                                                        @input="
                                                            calculateSubtotal(
                                                                item
                                                            )
                                                        "
                                                    />
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div
                                                        class="text-gray-700 font-medium"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                calculateSubtotal(
                                                                    item
                                                                )
                                                            )
                                                        }}
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <button
                                                        type="button"
                                                        @click="
                                                            removeItem(index)
                                                        "
                                                        class="text-red-600 hover:text-red-900"
                                                        :disabled="
                                                            form.items
                                                                .length === 1
                                                        "
                                                    >
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            class="h-5 w-5"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                            />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td
                                                    colspan="5"
                                                    class="px-6 py-4"
                                                >
                                                    <button
                                                        type="button"
                                                        @click="addItem"
                                                        class="flex items-center text-indigo-600 hover:text-indigo-900"
                                                    >
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            class="h-5 w-5 mr-1"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 4v16m8-8H4"
                                                            />
                                                        </svg>
                                                        Add Another Item
                                                    </button>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Claim Total -->
                            <div class="flex justify-end mt-6">
                                <div
                                    class="bg-gray-50 p-4 rounded-lg shadow-sm w-full md:w-1/3"
                                >
                                    <div
                                        class="flex justify-between items-center"
                                    >
                                        <h4
                                            class="text-base font-medium text-gray-700"
                                        >
                                            Total Claim Amount:
                                        </h4>
                                        <div
                                            class="text-lg font-bold text-gray-900"
                                        >
                                            {{
                                                formatCurrency(
                                                    form.total_amount
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Section -->
                            <div
                                class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200"
                            >
                                <PrimaryButton
                                    type="submit"
                                    class="ml-4 px-6 py-3"
                                    :disabled="form.processing"
                                >
                                    <span v-if="form.processing"
                                        >Processing...</span
                                    >
                                    <span v-else>Submit Claim</span>
                                </PrimaryButton>
                            
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    appearance: textfield;
    -moz-appearance: textfield;
}
</style>
