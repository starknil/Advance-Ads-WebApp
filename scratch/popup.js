const giveawayNotice = (() => {
  const defaults = {
    title: 'GIVEAWAY ALERT!',
    title_color: '#ffffff',
    background_color: '#1a1a2e',
    description: 'Participate now for a chance to win 10K SHIB! Limited time only!',
    description_color: '#e6e6e6',
    action_btn: 'JOIN NOW',
    btn_color: '#ff2d75', // Vibrant pink
    close_btn_color: '#ffffff', // White
    end_date: 20 // Contest ends on the 25th of each month
  };

  let popup;
  let countdownInterval;
  let overlay;

  function isContestActive() {
    const today = new Date().getDate();
    return today <= defaults.end_date;
  }

  function getNextContestDate() {
    const now = new Date();
    let nextMonth = now.getMonth() + 1;
    let nextYear = now.getFullYear();
    
    if (nextMonth > 11) {
      nextMonth = 0;
      nextYear++;
    }
    
    return new Date(nextYear, nextMonth, 1);
  }

  function formatDate(date) {
    const options = { month: 'long', day: 'numeric', year: 'numeric' };
    return date.toLocaleDateString('en-US', options);
  }

  function createCountdownTimer(endDate, container) {
    function updateTimer() {
      const now = new Date();
      const timeLeft = endDate - now;

      if (timeLeft <= 0) {
        clearInterval(countdownInterval);
        container.innerHTML = '<div style="color:#ff2d75;font-weight:bold;">CONTEST ENDED!</div>';
        return;
      }

      const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
      const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

      // Responsive font sizes
      const timeFontSize = window.innerWidth < 500 ? '1.2rem' : '1.8rem';
      const labelFontSize = window.innerWidth < 500 ? '0.6rem' : '0.7rem';
      const gapSize = window.innerWidth < 500 ? '5px' : '10px';

      container.innerHTML = `
        <div style="display:flex; justify-content:center; gap:${gapSize}; margin-bottom:15px;">
          <div style="background:#0f3460; padding:8px; border-radius:8px; min-width:50px;">
            <div style="font-size:${timeFontSize}; font-weight:bold; color:#00f5d4;">${days}</div>
            <div style="font-size:${labelFontSize}; color:#e6e6e6;">DAYS</div>
          </div>
          <div style="background:#0f3460; padding:8px; border-radius:8px; min-width:50px;">
            <div style="font-size:${timeFontSize}; font-weight:bold; color:#00f5d4;">${hours}</div>
            <div style="font-size:${labelFontSize}; color:#e6e6e6;">HOURS</div>
          </div>
          <div style="background:#0f3460; padding:8px; border-radius:8px; min-width:50px;">
            <div style="font-size:${timeFontSize}; font-weight:bold; color:#00f5d4;">${minutes}</div>
            <div style="font-size:${labelFontSize}; color:#e6e6e6;">MINUTES</div>
          </div>
          <div style="background:#0f3460; padding:8px; border-radius:8px; min-width:50px;">
            <div style="font-size:${timeFontSize}; font-weight:bold; color:#00f5d4;">${seconds}</div>
            <div style="font-size:${labelFontSize}; color:#e6e6e6;">SECONDS</div>
          </div>
        </div>
        <div style="font-size:0.9rem; color:#e6e6e6; margin-bottom:15px;">UNTIL CONTEST ENDS!</div>
      `;
    }

    updateTimer();
    countdownInterval = setInterval(updateTimer, 1000);
  }

  function createPopup(opts) {
    // Remove old if exists
    if (popup) popup.remove();
    if (countdownInterval) clearInterval(countdownInterval);
    if (overlay) overlay.remove();

    const isActive = isContestActive();
    const nextContestDate = getNextContestDate();

    // Create overlay for click outside
    overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
    overlay.style.zIndex = '9998';
    overlay.style.cursor = 'pointer';
    overlay.onclick = () => {
      popup.remove();
      overlay.remove();
      clearInterval(countdownInterval);
    };
    document.body.appendChild(overlay);

    popup = document.createElement('div');
    popup.style.position = 'fixed';
    popup.style.top = '50%';
    popup.style.left = '50%';
    popup.style.transform = 'translate(-50%, -50%)';
    popup.style.backgroundColor = opts.background_color;
    popup.style.border = '4px solid #00f5d4';
    popup.style.borderRadius = '20px';
    popup.style.padding = '25px';
    popup.style.width = '90vw';
    popup.style.maxWidth = '450px'; // Smaller max width
    popup.style.boxSizing = 'border-box';
    popup.style.textAlign = 'center';
    popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.5)';
    popup.style.fontFamily = '"Poppins", "Arial", sans-serif';
    popup.style.color = opts.description_color;
    popup.style.zIndex = '9999';
    popup.style.userSelect = 'none';

    // Gradient background effect
    const gradientBg = document.createElement('div');
    gradientBg.style.position = 'absolute';
    gradientBg.style.top = '0';
    gradientBg.style.left = '0';
    gradientBg.style.width = '100%';
    gradientBg.style.height = '100%';
    gradientBg.style.background = 'radial-gradient(circle at 20% 30%, rgba(255,45,117,0.15) 0%, rgba(0,245,212,0.1) 60%, rgba(26,26,46,0.9) 100%)';
    gradientBg.style.zIndex = '-1';
    popup.appendChild(gradientBg);

    // Confetti decoration elements (simplified)
    for (let i = 0; i < 8; i++) {
      const confetti = document.createElement('div');
      confetti.style.position = 'absolute';
      confetti.style.width = `${Math.random() * 8 + 3}px`;
      confetti.style.height = `${Math.random() * 8 + 3}px`;
      confetti.style.backgroundColor = ['#ff2d75', '#00f5d4', '#f9d71c'][Math.floor(Math.random() * 3)];
      confetti.style.top = `${Math.random() * 100}%`;
      confetti.style.left = `${Math.random() * 100}%`;
      confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
      confetti.style.opacity = '0.6';
      confetti.style.zIndex = '-1';
      popup.appendChild(confetti);
    }

    // Close button with background
    const closeBtn = document.createElement('div');
    closeBtn.innerHTML = '&#10005;';
    closeBtn.style.position = 'absolute';
    closeBtn.style.top = '10px';
    closeBtn.style.right = '10px';
    closeBtn.style.cursor = 'pointer';
    closeBtn.style.fontSize = '1.2rem';
    closeBtn.style.color = opts.close_btn_color;
    closeBtn.style.backgroundColor = 'rgba(0,0,0,0.3)';
    closeBtn.style.width = '30px';
    closeBtn.style.height = '30px';
    closeBtn.style.borderRadius = '50%';
    closeBtn.style.display = 'flex';
    closeBtn.style.alignItems = 'center';
    closeBtn.style.justifyContent = 'center';
    closeBtn.style.fontWeight = 'bold';
    closeBtn.style.transition = 'all 0.2s';
    closeBtn.onmouseover = () => {
      closeBtn.style.transform = 'scale(1.1)';
      closeBtn.style.backgroundColor = 'rgba(0,0,0,0.5)';
    };
    closeBtn.onmouseout = () => {
      closeBtn.style.transform = 'scale(1)';
      closeBtn.style.backgroundColor = 'rgba(0,0,0,0.3)';
    };
    closeBtn.onclick = (e) => {
      e.stopPropagation();
      popup.remove();
      overlay.remove();
      clearInterval(countdownInterval);
    };
    popup.appendChild(closeBtn);

    // Title
    const titleEl = document.createElement('h2');
    titleEl.textContent = isActive ? opts.title.toUpperCase() : 'CONTEST ENDED';
    titleEl.style.margin = '0 0 15px';
    titleEl.style.color = opts.title_color;
    titleEl.style.fontSize = '1.5rem';
    titleEl.style.fontWeight = '800';
    titleEl.style.letterSpacing = '1px';
    titleEl.style.textShadow = '0 2px 4px rgba(0,0,0,0.3)';
    popup.appendChild(titleEl);

    // Description
    const descEl = document.createElement('p');
    descEl.textContent = isActive ? opts.description : `New submissions open on ${formatDate(nextContestDate)}. Check back then!`;
    descEl.style.margin = '0 0 20px';
    descEl.style.fontWeight = '500';
    descEl.style.fontSize = '1rem';
    descEl.style.lineHeight = '1.5';
    descEl.style.color = opts.description_color;
    popup.appendChild(descEl);

    // Timer or next contest info
    const timerContainer = document.createElement('div');
    timerContainer.style.margin = '0 0 20px';
    
    if (isActive) {
      const endDate = new Date();
      endDate.setDate(opts.end_date);
      endDate.setHours(23, 59, 59, 999);
      createCountdownTimer(endDate, timerContainer);
    } else {
      timerContainer.innerHTML = `
        <div style="background: rgba(0,245,212,0.1); padding:12px; border-radius:10px; border:1px dashed #00f5d4;">
          <div style="font-size:1rem; font-weight:bold; color:#00f5d4; margin-bottom:5px;">NEXT CONTEST STARTS:</div>
          <div style="font-size:1.2rem; font-weight:bold; color:#ffffff;">${formatDate(nextContestDate)}</div>
        </div>
      `;
    }
    popup.appendChild(timerContainer);

    // Prize showcase (10K SHIB only)
    const prizeShowcase = document.createElement('div');
    prizeShowcase.style.margin = '0 0 20px';
    prizeShowcase.style.padding = '12px';
    prizeShowcase.style.backgroundColor = 'rgba(255,255,255,0.05)';
    prizeShowcase.style.borderRadius = '10px';
    prizeShowcase.style.border = '1px solid rgba(255,255,255,0.1)';
    prizeShowcase.innerHTML = `
      <div style="font-size:0.9rem; font-weight:bold; color:#00f5d4; margin-bottom:8px;">PRIZE TO WIN:</div>
      <div style="display: flex; justify-content: center; align-items: center; gap: 10px;">
        <div style="height: 50px; width: 50px; background: rgba(0,245,212,0.1); border-radius:8px; display:flex; align-items:center; justify-content:center;">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="#00f5d4">
            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11V11.99z"/>
          </svg>
        </div>
        <div style="text-align:left;">
          <div style="font-size:1.2rem; font-weight:bold; color:#ffffff;">10,000 SHIB</div>
          <div style="font-size:0.8rem; color:rgba(255,255,255,0.7);">(3 Winner)</div>
        </div>
      </div>
    `;
    popup.appendChild(prizeShowcase);

    // Terms text
    const terms = document.createElement('div');
    terms.style.fontSize = '0.65rem';
    terms.style.color = 'rgba(255,255,255,0.5)';
    terms.style.margin = '0 0 15px';
    terms.style.lineHeight = '1.4';
    terms.textContent = 'By participating, you agree to our terms and conditions. Winners will be notified via email.';
    popup.appendChild(terms);

    // Action button (simpler version)
    if (isActive) {
      const actionBtn = document.createElement('button');
      actionBtn.textContent = opts.action_btn.toUpperCase();
      actionBtn.style.border = 'none';
      actionBtn.style.background = opts.btn_color;
      actionBtn.style.color = 'white';
      actionBtn.style.fontWeight = 'bold';
      actionBtn.style.fontSize = '0.9rem';
      actionBtn.style.padding = '8px 20px';
      actionBtn.style.borderRadius = '4px';
      actionBtn.style.cursor = 'pointer';
      actionBtn.style.margin = '5px 0';
      actionBtn.style.transition = 'all 0.2s';
      actionBtn.onmouseover = () => {
        actionBtn.style.opacity = '0.9';
      };
      actionBtn.onmouseout = () => {
        actionBtn.style.opacity = '1';
      };
      actionBtn.onclick = (e) => {
        e.stopPropagation();
        if (typeof opts.action === 'function') opts.action();
      };
      popup.appendChild(actionBtn);
    }

    // Add to document
    document.body.appendChild(popup);

    // Add animation
    popup.style.opacity = '0';
    popup.style.transform = 'translate(-50%, -50%) scale(0.95)';
    setTimeout(() => {
      popup.style.transition = 'all 0.2s ease-out';
      popup.style.opacity = '1';
      popup.style.transform = 'translate(-50%, -50%) scale(1)';
    }, 10);
  }

  return {
    show: (opts = {}) => {
      const settings = {...defaults, ...opts};
      createPopup(settings);
    }
  };
})();