<template>
    <div class="p-8">
        <div class="max-w-7xl mx-auto">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-semibold text-gray-900">Support Tickets</h1>
                <button
                    @click="showForm = !showForm"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                >
                    New Ticket
                </button>
            </div>

            <!-- New Ticket Form -->
            <div v-if="showForm" class="mb-8 bg-white border border-gray-200 rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Create Ticket</h2>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <input
                            v-model="form.subject"
                            type="text"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Body</label>
                        <textarea
                            v-model="form.body"
                            rows="3"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select v-model="form.category" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                <option value="">Select...</option>
                                <option value="billing">Billing</option>
                                <option value="technical">Technical</option>
                                <option value="outage">Outage</option>
                                <option value="general">General</option>
                                <option value="account">Account</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Tier</label>
                            <select v-model="form.customer_tier" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                <option value="">Select...</option>
                                <option value="free">Free</option>
                                <option value="starter">Starter</option>
                                <option value="professional">Professional</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Response Time (hours)</label>
                            <input
                                v-model="form.response_time_expectation"
                                type="number"
                                min="1"
                                max="72"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button
                            @click="showForm = false"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900"
                        >
                            Cancel
                        </button>
                        <button
                            @click="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                        >
                            Submit
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Predicted Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Confidence</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="ticket in tickets.data" :key="ticket.id">
                            <td class="px-6 py-4 text-sm text-gray-500">#{{ ticket.id }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ ticket.subject }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 capitalize">{{ ticket.category }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 capitalize">{{ ticket.customer_tier }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span :class="priorityClass(ticket.predicted_priority)" class="px-2 py-1 rounded-full text-xs font-medium">
                                    {{ ticket.predicted_priority ?? '—' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ ticket.confidence_score ? (ticket.confidence_score * 100).toFixed(1) + '%' : '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span :class="statusClass(ticket.triage_status)" class="px-2 py-1 rounded-full text-xs font-medium">
                                    {{ ticket.triage_status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center text-sm text-gray-500">
                    <span>Showing {{ tickets.from }}–{{ tickets.to }} of {{ tickets.total }}</span>
                    <div class="flex gap-2">
                        <a
                            v-if="tickets.prev_page_url"
                            :href="tickets.prev_page_url"
                            class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50"
                        >
                            Previous
                        </a>
                        <a
                            v-if="tickets.next_page_url"
                            :href="tickets.next_page_url"
                            class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50"
                        >
                            Next
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

defineProps({
    tickets: Object,
})

const showForm = ref(false)

const form = useForm({
    subject: '',
    body: '',
    category: '',
    customer_tier: '',
    response_time_expectation: '',
})

const submit = () => {
    form.post(route('tickets.store'), {
        onSuccess: () => {
            showForm.value = false
            form.reset()
        },
    })
}

const priorityClass = (priority) => ({
    'bg-red-100 text-red-700':    priority === 'critical',
    'bg-orange-100 text-orange-700': priority === 'high',
    'bg-yellow-100 text-yellow-700': priority === 'medium',
    'bg-green-100 text-green-700':   priority === 'low',
    'bg-gray-100 text-gray-500':     !priority,
})

const statusClass = (status) => ({
    'bg-green-100 text-green-700':  status === 'complete',
    'bg-yellow-100 text-yellow-700': status === 'pending',
    'bg-red-100 text-red-700':      status === 'failed',
})
</script>