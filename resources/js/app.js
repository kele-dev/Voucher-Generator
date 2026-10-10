import './bootstrap';
import Alpine from 'alpinejs';
import templateEditor from './template-editor';
import campaignForm from './campaign-form';
import batchRow from './batch-row';
import voucherPreview from './voucher-preview';
import confirmStore from './confirm';
import toastStore from './toasts';
import countUp from './counter';
import copyButton from './clipboard';

window.Alpine = Alpine;

Alpine.store('confirm', confirmStore);
Alpine.store('toasts', toastStore);

Alpine.data('templateEditor', templateEditor);
Alpine.data('campaignForm', campaignForm);
Alpine.data('batchRow', batchRow);
Alpine.data('voucherPreview', voucherPreview);
Alpine.data('countUp', countUp);
Alpine.data('copyButton', copyButton);

Alpine.start();
