
export function formatPhoneNumber(jid) {
    if (jid.includes('@g.us')) {
        return null; 
    }

    const phoneNumber = jid.split('@')[0];
    if (phoneNumber.startsWith('62')) {
        return `+${phoneNumber}`;
    }
    return `+${phoneNumber}`;
}


// export function isAllowedNumber(jid, allowedNumbers) {
//     const phoneNumber = jid.split('@')[0];
//     return allowedNumbers.includes(phoneNumber);
// }

export function isAllowedNumber(jid, allowedNumbers) {
    if (allowedNumbers.includes("*")) {
        return true;
    }
    
    const phoneNumber = jid.split('@')[0];
    return allowedNumbers.includes(phoneNumber);
}

export function generateId() {
    return Date.now().toString() + Math.random().toString(36).substr(2, 9);
}

export function validateMessageData(data) {
    const errors = [];
    
    if (!data.message && !data.type) {
        errors.push('Message content or type is required');
    }
    
    if (data.type && !['user_message', 'bot_response', 'bot_error'].includes(data.type)) {
        errors.push('Invalid message type');
    }
    
    return {
        isValid: errors.length === 0,
        errors
    };
}

export function sanitizeInput(input) {
    if (typeof input !== 'string') return input;
    
    return input
        .trim()
        .replace(/[<>]/g, '') 
        .substring(0, 1000); 
}

export function parseCommand(message) {
    const parts = message.trim().split(' ');
    const command = parts[0].toLowerCase();
    const args = parts.slice(1);
    
    return {
        command,
        args,
        fullMessage: message
    };
}

export function isValidJid(jid) {
    if (typeof jid !== 'string') return false;
    return jid.includes('@') && (jid.endsWith('@s.whatsapp.net') || jid.endsWith('@g.us'));
}

export function extractGroupId(jid) {
    if (!jid.includes('@g.us')) return null;
    return jid.split('@')[0];
}

export function formatTimestamp(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleString('id-ID', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

export async function retry(fn, retries = 3, delay = 1000) {
    for (let i = 0; i < retries; i++) {
        try {
            return await fn();
        } catch (error) {
            if (i === retries - 1) throw error;
            
            const backoffDelay = delay * Math.pow(2, i);
            await new Promise(resolve => setTimeout(resolve, backoffDelay));
        }
    }
}
