const statuses = {
    draft: { text: __('Draft'), color: 'default' },
    scheduled: { text: __('Scheduled'), color: 'blue' },
    sending: { text: __('Sending'), color: 'yellow' },
    sent: { text: __('Sent'), color: 'green' },
    cancelled: { text: __('Cancelled'), color: 'red' },
};

export const campaignStatus = (status) => statuses[status] ?? { text: status, color: 'default' };
