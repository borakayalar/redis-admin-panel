const parsePhpSerialized = (str) => {
    try {
        const encoder = new TextEncoder();
        const decoder = new TextDecoder();
        const bytes = encoder.encode(str);
        let offset = 0;

        const readUntil = (char) => {
            const charByte = char.charCodeAt(0);
            const start = offset;
            while (offset < bytes.length && bytes[offset] !== charByte) offset++;
            const res = decoder.decode(bytes.slice(start, offset));
            offset++;
            return res;
        };

        const parse = () => {
            if (offset >= bytes.length) throw new Error('EOF');
            const type = String.fromCharCode(bytes[offset]);

            if (type === 'N') { offset += 2; return null; }
            offset += 2;

            if (type === 'b') return readUntil(';') === '1';
            if (type === 'i' || type === 'd') return Number(readUntil(';'));

            if (type === 's') {
                const len = parseInt(readUntil(':'), 10);
                offset += 1;
                const val = decoder.decode(bytes.slice(offset, offset + len));
                offset += len + 2;
                return val;
            }

            if (type === 'a') {
                const len = parseInt(readUntil(':'), 10);
                offset += 1;
                const result = {};
                for (let i = 0; i < len; i++) {
                    const key = parse();
                    const val = parse();
                    result[key] = val;
                }
                offset += 1;
                return result;
            }

            if (type === 'O') {
                const classLen = parseInt(readUntil(':'), 10);
                offset += 1;
                const className = decoder.decode(bytes.slice(offset, offset + classLen));
                offset += classLen + 2;
                const propCount = parseInt(readUntil(':'), 10);
                offset += 1;
                const result = { __className: className };
                for (let i = 0; i < propCount; i++) {
                    let key = parse();
                    if (typeof key === 'string') key = key.replace(/\0/g, '').trim();
                    const val = parse();
                    result[key] = val;
                }
                offset += 1;
                return result;
            }
            throw new Error('Unsupported type');
        };
        return parse();
    } catch (e) {
        return false;
    }
};

export const formatValue = (val) => {
    if (typeof val !== 'string') return val;
    let result = val;
    let parsed = false;

    try {
        const jsonObj = JSON.parse(val);
        if (typeof jsonObj === 'object' && jsonObj !== null) {
            result = jsonObj;
            parsed = true;
        }
    } catch(e) {}

    if (!parsed && /^[aOsibN][:;]/.test(val)) {
        const phpObj = parsePhpSerialized(val);
        if (phpObj !== false && phpObj !== null) {
            if (typeof phpObj === 'object') {
                result = phpObj;
                parsed = true;
            } else if (typeof phpObj === 'string') {
                const innerFormat = formatValue(phpObj);
                result = innerFormat;
                parsed = typeof innerFormat === 'object';
            }
        }
    }

    if (parsed && typeof result === 'object' && result !== null) {
        for (let key in result) {
            if (typeof result[key] === 'string') {
                const inner = formatValue(result[key]);
                if (typeof inner === 'object') result[key] = inner;
            }
        }
        return result;
    }
    return result;
};

export const isComplex = (val) => {
    const formatted = formatValue(val);
    return typeof formatted === 'object' && formatted !== null;
};
