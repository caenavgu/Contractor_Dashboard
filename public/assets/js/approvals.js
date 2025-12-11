// public/assets/js/approvals.js    
    function confirmReject(formEl){
        const reason = prompt('Please enter a short reason:','Data could not be verified');
        if (reason === null) return false;
        formEl.querySelector('input[name="reason"]').value = reason.trim();
        return true;
    }

    function askContractorId(formEl, keepOnly=false){
        const msg = keepOnly
        ? 'Enter the existing contractor_id to KEEP (discard staging):'
        : 'Enter the existing contractor_id to MERGE INTO (existing record will be updated except CAC):';
        const id = prompt(msg,'');
        if (!id) return false;
        formEl.querySelector('input[name="contractor_id"]').value = id.trim();
        return true;
    }