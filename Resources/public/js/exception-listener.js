let ExceptionListener = function(config) {
    window.onerror = function (msg, url, lineNo, columnNo, error) {
        let stack = [];
        if (error) {
            stack = error.stack.split(/[\r?\n][ ]*/);
        }
        let cookies = {};
        
        document.cookie
            .split(';')
            .forEach(cookie => {
                let details = cookie.split('=');
                cookies[details[0]] = (details[0] === 'PHPSESSID') ? '******' : details[1];
            })
        ;
        
        let xhttp = new XMLHttpRequest();
        xhttp.open("POST", config.endpoint, true);
        xhttp.setRequestHeader("Content-Type", "application/json");
        xhttp.send(JSON.stringify({
            line: lineNo,
            file: url,
            url: document.location.href,
            user: config.user || null,
            app_version: config.version || null,
            message: msg,
            trace: stack,
            language: 'js',
            extra: {
                COOKIES: cookies,
                user_agent: navigator.userAgent,
            }
        }));
        return false;
    };
};


