// Alpine component for the campaign form: filters templates/outlets by the
// chosen brand, shows a live example of the voucher code pattern, edits the
// menu list and refreshes a server-rendered preview of the voucher. The code
// example mirrors App\Services\Voucher\CodePattern; the server re-validates.

import voucherPreview from './voucher-preview.js';

const TOKEN = /\{(PREFIX|DDMM|DATE:[dmyY]{1,6}|SEQ:[0-9]{1,2}|RAND:[0-9]{1,2}|ALPHA:[0-9]{1,2})\}/g;
const MAX_MENU = 10;

// Form input -> template slots that print it (config/voucher.php fields).
const SLOT_INPUTS = [
    { input: 'name', slots: ['campaign_title'], label: 'Nama campaign' },
    { input: 'benefit_title', slots: ['benefit_title'], label: 'Judul benefit' },
    { input: 'benefit_description', slots: ['benefit_description'], label: 'Deskripsi benefit' },
    { input: 'menu_items', slots: ['menu_items', 'free_portion'], label: 'Menu & porsi' },
    { input: 'normal_value', slots: ['normal_value'], label: 'Nilai normal' },
    { input: 'min_purchase', slots: ['min_purchase'], label: 'Minimal belanja' },
    { input: 'service_type', slots: ['service_type'], label: 'Jenis layanan' },
    { input: 'valid_schedule', slots: ['valid_schedule'], label: 'Hari & jam' },
    { input: 'terms', slots: ['terms'], label: 'Syarat & ketentuan' },
];

// Which of the inputs above currently hold a value.
function filledInputs(data) {
    const has = (key) => String(data.get(key) ?? '').trim() !== '';
    const filled = ['name', 'benefit_title', 'benefit_description', 'normal_value', 'min_purchase', 'terms'].filter(has);
    if ([...data.keys()].some((k) => /^menu_items\[\d+\]\[name\]$/.test(k) && String(data.get(k)).trim() !== '')) filled.push('menu_items');
    if ((data.get('service_type') ?? 'any') !== 'any') filled.push('service_type');
    if (data.getAll('valid_days[]').length || has('valid_time_from')) filled.push('valid_schedule');
    return filled;
}

function formatDate(date, format) {
    const pad = (n) => String(n).padStart(2, '0');
    const map = {
        d: pad(date.getDate()),
        m: pad(date.getMonth() + 1),
        y: String(date.getFullYear()).slice(-2),
        Y: String(date.getFullYear()),
    };
    return [...format].map((c) => map[c] ?? c).join('');
}

export default function campaignForm(config) {
    return {
        ...voucherPreview({ url: config.previewUrl }),
        ...config,
        menuItems: (config.menuItems?.length ? config.menuItems : [{ name: '', qty: 1 }]).map((item) => ({
            name: item.name ?? '',
            qty: Number(item.qty) || 1,
        })),
        _previewTimer: null,
        filled: [],
        pickedImage: null, // { name, url } of a product photo chosen but not saved yet

        init() {
            this.$nextTick(() => this.refreshPreview(0));
        },

        get filteredTemplates() {
            return this.templates.filter((t) => String(t.brand_id) === String(this.brandId));
        },

        get filteredOutlets() {
            return this.outlets.filter((o) => String(o.brand_id) === String(this.brandId));
        },

        get activePattern() {
            return this.preset === 'custom' ? this.pattern.toUpperCase() : this.presets[this.preset]?.pattern ?? '';
        },

        get guessable() {
            return !/\{(RAND|ALPHA):\d+\}/.test(this.activePattern);
        },

        get seqCapacity() {
            const match = this.activePattern.match(/\{SEQ:(\d+)\}/);
            return match ? 10 ** Number(match[1]) - 1 : null;
        },

        get example() {
            const pattern = this.activePattern;
            if (!/\{(SEQ|RAND|ALPHA):\d+\}/.test(pattern)) return '';

            return pattern.replace(TOKEN, (_, token) => {
                const [name, arg] = token.split(':');
                if (name === 'PREFIX') return (this.prefix || 'BB-VK').toUpperCase();
                if (name === 'DDMM') return formatDate(new Date(), 'dm');
                if (name === 'DATE') return formatDate(new Date(), arg);
                if (name === 'SEQ') return '1'.padStart(Number(arg), '0');
                if (name === 'RAND') {
                    return Array.from({ length: Number(arg) }, () => this.charset[Math.floor(Math.random() * this.charset.length)]).join('');
                }
                if (name === 'ALPHA') {
                    const alpha = this.alphaCharset || 'ABCDEFGHJKLMNPQRSTUVWXYZ';
                    return Array.from({ length: Number(arg) }, () => alpha[Math.floor(Math.random() * alpha.length)]).join('');
                }
                return '';
            });
        },

        get selectedTemplate() {
            return this.templates.find((t) => String(t.id) === String(this.templateId)) ?? null;
        },

        // Filled inputs the chosen template has no slot for: they are saved
        // and frozen onto vouchers, but the image will not show them.
        get unprintedInputs() {
            const template = this.selectedTemplate;
            if (!template) return [];
            const slots = new Set(template.slots);
            return SLOT_INPUTS
                .filter((input) => this.filled.includes(input.input) && !input.slots.some((slot) => slots.has(slot)))
                .map((input) => input.label);
        },

        get totalPortions() {
            return this.menuItems.filter((i) => i.name.trim()).reduce((sum, i) => sum + (Number(i.qty) || 0), 0);
        },

        get canAddMenu() {
            return this.menuItems.length < MAX_MENU;
        },

        addMenu() {
            if (!this.canAddMenu) return;
            this.menuItems.push({ name: '', qty: 1 });
            this.$nextTick(() => this.$root.querySelector(`#menu_items_${this.menuItems.length - 1}_name`)?.focus());
        },

        removeMenu(index) {
            this.menuItems.splice(index, 1);
            if (!this.menuItems.length) this.menuItems.push({ name: '', qty: 1 });
            this.refreshPreview();
        },

        stepQty(index, delta) {
            const item = this.menuItems[index];
            item.qty = Math.min(99, Math.max(1, (Number(item.qty) || 1) + delta));
            this.refreshPreview();
        },

        // Local thumbnail only: the file is not part of the server preview
        // (refreshPreview drops it) and is uploaded when the form is saved.
        pickImage(event) {
            if (this.pickedImage) URL.revokeObjectURL(this.pickedImage.url);
            const file = event.target.files[0];
            this.pickedImage = file ? { name: file.name, url: URL.createObjectURL(file) } : null;
        },

        rupiah(value) {
            return value === '' || value === null || value === undefined
                ? ''
                : 'Rp' + Number(value).toLocaleString('id-ID');
        },

        updatePrefix() {
            const brand = this.brands.find((b) => String(b.id) === String(this.brandId));
            if (brand) {
                const type = (this.voucherType || 'VM').toUpperCase();
                this.prefix = `${brand.code_prefix}-${type}`;
            }
        },

        matchTemplateByKind() {
            const kind = this.voucherKind;
            const templates = this.filteredTemplates;
            if (!templates.length) return;

            // The four kinds map onto two axes: nominal (money) and digital
            // (e-voucher). Brand-synced templates are named accordingly, so we
            // match the template whose name carries the same pair of axis values.
            const wantsNominal = kind === 'nominal' || kind === 'electronic_nominal';
            const wantsDigital = kind === 'electronic' || kind === 'electronic_nominal';

            const matched = templates.find((t) => {
                const name = String(t.name).toLowerCase();
                const templateNominal = name.includes('nominal');
                const templateDigital = /e-voucher|electronic|digital/.test(name);
                return templateNominal === wantsNominal && templateDigital === wantsDigital;
            });

            if (matched) {
                this.templateId = matched.id;
            } else if (!templates.some((t) => String(t.id) === String(this.templateId))) {
                this.templateId = templates[0]?.id ?? '';
            }
        },

        changeBrand() {
            this.updatePrefix();
            this.matchTemplateByKind();
            this.refreshPreview();
        },

        changeVoucherKind() {
            this.matchTemplateByKind();
            this.refreshPreview();
        },

        changeVoucherType() {
            this.updatePrefix();
            this.refreshPreview();
        },

        // Debounced: typing fires many events, the server renders once.
        refreshPreview(delay = 600) {
            clearTimeout(this._previewTimer);
            this._previewTimer = setTimeout(() => {
                this.filled = filledInputs(new FormData(this.$root));
                if (!this.templateId) {
                    this.error = 'Pilih template untuk melihat pratinjau.';
                    return;
                }
                const data = new FormData(this.$root);
                // The form's own method spoofing and file upload must not
                // reach the preview endpoint.
                data.delete('_method');
                data.delete('product_image');
                data.set('code_example', this.example);
                if (this.campaignId) data.set('campaign_id', this.campaignId);
                this.load(data);
            }, delay);
        },
    };
}
