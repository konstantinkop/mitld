/**
 * NordicAdmin – Dansk Kontrolpanel & Trafikanalyse
 * Interaktiv JavaScript demo med Chart.js og simuleret realtidsdata
 */

document.addEventListener('DOMContentLoaded', () => {
  // =========================================================================
  // INITIALISERING & DANSK FORMATERING
  // =========================================================================

  const daFormatter = new Intl.NumberFormat('da-DK');
  
  function formatTal(tal) {
    return daFormatter.format(tal);
  }

  function getDanskTidspunkt(dato = new Date()) {
    const timer = String(dato.getHours()).padStart(2, '0');
    const minutter = String(dato.getMinutes()).padStart(2, '0');
    const sekunder = String(dato.getSeconds()).padStart(2, '0');
    return `${timer}:${minutter}:${sekunder}`;
  }

  // =========================================================================
  // DEMODATA SÆT EFTER DATOINTERVAL
  // =========================================================================

  const dataSæt = {
    today: {
      navn: "I dag (15. okt. 2026)",
      besoeg: 3418,
      sidevisninger: 11892,
      aktive: 48,
      gnsVarighed: "3m 42s",
      linjeLabels: ["00:00", "03:00", "06:00", "09:00", "12:00", "15:00", "18:00", "21:00"],
      linjeDataNu: [120, 85, 210, 840, 1150, 980, 720, 410],
      linjeDataFoer: [100, 70, 180, 760, 990, 890, 640, 380],
      soejleLabels: ["08:00-10:00", "10:00-12:00", "12:00-14:00", "14:00-16:00", "16:00-18:00", "18:00-20:00"],
      soejleData: [620, 890, 1150, 780, 540, 410]
    },
    "7days": {
      navn: "Seneste 7 dage",
      besoeg: 24890,
      sidevisninger: 86450,
      aktive: 52,
      gnsVarighed: "3m 54s",
      linjeLabels: ["Fredag", "Lørdag", "Søndag", "Mandag", "Tirsdag", "Onsdag", "Torsdag"],
      linjeDataNu: [3200, 2450, 2100, 3890, 4120, 4350, 4780],
      linjeDataFoer: [2980, 2200, 1950, 3600, 3850, 4010, 4200],
      soejleLabels: ["Fredag", "Lørdag", "Søndag", "Mandag", "Tirsdag", "Onsdag", "Torsdag"],
      soejleData: [3200, 2450, 2100, 3890, 4120, 4350, 4780]
    },
    "30days": {
      navn: "Seneste 30 dage",
      besoeg: 104250,
      sidevisninger: 362400,
      aktive: 45,
      gnsVarighed: "4m 02s",
      linjeLabels: ["Uge 38", "Uge 39", "Uge 40", "Uge 41", "Uge 42"],
      linjeDataNu: [19800, 22400, 24100, 26800, 25200],
      linjeDataFoer: [18200, 20100, 21500, 23900, 22800],
      soejleLabels: ["Uge 38", "Uge 39", "Uge 40", "Uge 41", "Uge 42"],
      soejleData: [19800, 22400, 24100, 26800, 25200]
    },
    month: {
      navn: "Denne måned (oktober)",
      besoeg: 58740,
      sidevisninger: 204310,
      aktive: 50,
      gnsVarighed: "3m 49s",
      linjeLabels: ["1.-5. okt", "6.-10. okt", "11.-15. okt", "16.-20. okt", "21.-25. okt", "26.-31. okt"],
      linjeDataNu: [9200, 11400, 12600, 11800, 9500, 4240],
      linjeDataFoer: [8500, 10200, 11000, 10800, 8900, 3900],
      soejleLabels: ["1.-5. okt", "6.-10. okt", "11.-15. okt", "16.-20. okt", "21.-25. okt"],
      soejleData: [9200, 11400, 12600, 11800, 9500]
    },
    quarter: {
      navn: "Sidste kvartal",
      besoeg: 312800,
      sidevisninger: 1087400,
      aktive: 42,
      gnsVarighed: "3m 38s",
      linjeLabels: ["Juli", "August", "September"],
      linjeDataNu: [94500, 106200, 112100],
      linjeDataFoer: [88000, 98400, 104500],
      soejleLabels: ["Juli", "August", "September"],
      soejleData: [94500, 106200, 112100]
    }
  };

  // =========================================================================
  // TABEL DEMODATA: SENESTE SIDEVISNINGER
  // =========================================================================

  const siderBibliotek = [
    { sti: "/forside", titel: "Startside – Velkommen" },
    { sti: "/produkter/cloud-hosting", titel: "Erhverv Cloud Løsninger" },
    { sti: "/priser", titel: "Prisoversigt & Abonnementer" },
    { sti: "/om-os", titel: "Om virksomheden & Team" },
    { sti: "/kontakt", titel: "Kontakt os & Support" },
    { sti: "/blog/datasikkerhed-2026", titel: "Artikel: Datasikkerhed i 2026" },
    { sti: "/portal/login", titel: "Kundeportal Log på" },
    { sti: "/support/faq", titel: "Ofte stillede spørgsmål" },
    { sti: "/vilkaar", titel: "Betingelser og vilkår" }
  ];

  const kilderBibliotek = [
    "Google Søgning (Organisk)",
    "Direkte adresseindtastning",
    "LinkedIn Erhverv",
    "Nyhedsbrev kampagne",
    "Partnerhenvisning",
    "Google Ads (Betalt)"
  ];

  const enhederBibliotek = [
    { type: "Mobil", icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>` },
    { type: "Desktop", icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>` },
    { type: "Tablet", icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>` }
  ];

  const statusBibliotek = [
    { label: "Gennemført", klasse: "status-gennemfoert" },
    { label: "Aktiv", klasse: "status-aktiv" },
    { label: "Indlæst", klasse: "status-indlaest" }
  ];

  // Startrækker for tabellen
  let tabelData = [
    {
      side: siderBibliotek[0],
      tidspunkt: "Lige nu",
      kilde: "Google Søgning (Organisk)",
      enhed: enhederBibliotek[0],
      status: statusBibliotek[1] // Aktiv
    },
    {
      side: siderBibliotek[1],
      tidspunkt: "For 14 sek. siden",
      kilde: "LinkedIn Erhverv",
      enhed: enhederBibliotek[1],
      status: statusBibliotek[0] // Gennemført
    },
    {
      side: siderBibliotek[2],
      tidspunkt: "For 38 sek. siden",
      kilde: "Direkte adresseindtastning",
      enhed: enhederBibliotek[1],
      status: statusBibliotek[0] // Gennemført
    },
    {
      side: siderBibliotek[5],
      tidspunkt: "For 1 min. siden",
      kilde: "Nyhedsbrev kampagne",
      enhed: enhederBibliotek[0],
      status: statusBibliotek[2] // Indlæst
    },
    {
      side: siderBibliotek[4],
      tidspunkt: "For 2 min. siden",
      kilde: "Google Søgning (Organisk)",
      enhed: enhederBibliotek[0],
      status: statusBibliotek[0] // Gennemført
    },
    {
      side: siderBibliotek[6],
      tidspunkt: "For 3 min. siden",
      kilde: "Direkte adresseindtastning",
      enhed: enhederBibliotek[1],
      status: statusBibliotek[1] // Aktiv
    },
    {
      side: siderBibliotek[1],
      tidspunkt: "For 4 min. siden",
      kilde: "Partnerhenvisning",
      enhed: enhederBibliotek[2],
      status: statusBibliotek[0] // Gennemført
    },
    {
      side: siderBibliotek[3],
      tidspunkt: "For 6 min. siden",
      kilde: "Google Søgning (Organisk)",
      enhed: enhederBibliotek[1],
      status: statusBibliotek[2] // Indlæst
    }
  ];

  // =========================================================================
  // DOM ELEMENTER
  // =========================================================================

  const activeVisitorsElem = document.getElementById('activeVisitorsCount');
  const activeVisitorsTrendElem = document.getElementById('activeVisitorsTrend');
  const todayVisitsElem = document.getElementById('todayVisitsCount');
  const pageviewsElem = document.getElementById('pageviewsCount');
  const avgDurationElem = document.getElementById('avgDurationCount');
  const dateRangeSelect = document.getElementById('dateRangeSelect');
  const refreshBtn = document.getElementById('refreshDataBtn');
  const toggleSimBtn = document.getElementById('toggleSimBtn');
  const lastUpdatedNotice = document.getElementById('lastUpdatedNotice');
  const tableBody = document.getElementById('pageviewsTableBody');
  const tableSearchInput = document.getElementById('tableSearchInput');
  const filterChips = document.querySelectorAll('.chip');
  const tableRowCountElem = document.getElementById('tableRowCount');
  const clearTableLogBtn = document.getElementById('clearTableLogBtn');
  const loadMoreRowsBtn = document.getElementById('loadMoreRowsBtn');
  const toastNotification = document.getElementById('toastNotification');
  const toastMessage = document.getElementById('toastMessage');

  // Mobil menu elementer
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebar = document.getElementById('sidebar');
  const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');

  // Simulationstilstand
  let isSimulating = true;
  let aktivtFilter = "alle";
  let soegetekst = "";
  let simulationInterval = null;

  // =========================================================================
  // TOAST NOTIFIKATION FUNKTION
  // =========================================================================

  let toastTimer = null;
  function visToast(besked) {
    if (toastTimer) clearTimeout(toastTimer);
    toastMessage.textContent = besked;
    toastNotification.classList.add('show');
    toastTimer = setTimeout(() => {
      toastNotification.classList.remove('show');
    }, 3200);
  }

  // =========================================================================
  // INITIALISERING AF CHART.JS DIAGRAMMER
  // =========================================================================

  let visitorChartInstance = null;
  let dailyVisitsChartInstance = null;
  let deviceChartInstance = null;

  function initCharts() {
    if (typeof Chart === 'undefined') {
      console.warn('Chart.js kunne ikke indlæses via CDN.');
      return;
    }

    // Chart.js globale indstillinger for ensartet skandinavisk design
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.color = "#64748b";
    Chart.defaults.scale.grid.color = "#f1f5f9";

    // 1. LINJEDIAGRAM: Besøgsstatistik
    const ctxVisitor = document.getElementById('visitorTrafficChart');
    if (ctxVisitor) {
      const gradientNu = ctxVisitor.getContext('2d').createLinearGradient(0, 0, 0, 260);
      gradientNu.addColorStop(0, 'rgba(37, 99, 235, 0.28)');
      gradientNu.addColorStop(1, 'rgba(37, 99, 235, 0.00)');

      visitorChartInstance = new Chart(ctxVisitor, {
        type: 'line',
        data: {
          labels: dataSæt["7days"].linjeLabels,
          datasets: [
            {
              label: 'Nuværende periode',
              data: dataSæt["7days"].linjeDataNu,
              borderColor: '#2563eb',
              backgroundColor: gradientNu,
              borderWidth: 2.5,
              tension: 0.38,
              fill: true,
              pointBackgroundColor: '#2563eb',
              pointBorderColor: '#ffffff',
              pointBorderWidth: 2,
              pointRadius: 4,
              pointHoverRadius: 6
            },
            {
              label: 'Forrige periode',
              data: dataSæt["7days"].linjeDataFoer,
              borderColor: '#cbd5e1',
              borderDash: [5, 5],
              borderWidth: 2,
              tension: 0.38,
              fill: false,
              pointRadius: 0,
              pointHoverRadius: 4
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: {
            padding: {
              left: 20,
              right: 16,
              top: 14,
              bottom: 4
            }
          },
          plugins: {
            legend: {
              display: false // Egen tilpasset legend benyttes i HTML
            },
            tooltip: {
              backgroundColor: '#0f172a',
              titleColor: '#ffffff',
              bodyColor: '#e2e8f0',
              padding: 12,
              cornerRadius: 8,
              callbacks: {
                label: function(context) {
                  return ` ${context.dataset.label}: ${formatTal(context.parsed.y)} besøgende`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: false,
              ticks: {
                padding: 10,
                callback: function(val) {
                  return formatTal(val);
                }
              }
            },
            x: {
              grid: {
                display: false
              }
            }
          }
        }
      });
    }

    // 2. SØJLEDIAGRAM: Daglige besøg
    const ctxDaily = document.getElementById('dailyVisitsChart');
    if (ctxDaily) {
      dailyVisitsChartInstance = new Chart(ctxDaily, {
        type: 'bar',
        data: {
          labels: dataSæt["7days"].soejleLabels,
          datasets: [
            {
              label: 'Unikke besøg',
              data: dataSæt["7days"].soejleData,
              backgroundColor: '#3b82f6',
              hoverBackgroundColor: '#1d4ed8',
              borderRadius: 6,
              borderSkipped: false
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: {
            padding: {
              left: 20,
              right: 16,
              top: 14,
              bottom: 4
            }
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: '#0f172a',
              padding: 10,
              cornerRadius: 8,
              callbacks: {
                label: function(context) {
                  return ` Besøg: ${formatTal(context.parsed.y)}`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                padding: 10,
                callback: function(val) {
                  return formatTal(val);
                }
              }
            },
            x: {
              grid: {
                display: false
              }
            }
          }
        }
      });
    }

    // 3. DOUGHNUT DIAGRAM: Enhedsfordeling
    const ctxDevice = document.getElementById('deviceDoughnutChart');
    if (ctxDevice) {
      deviceChartInstance = new Chart(ctxDevice, {
        type: 'doughnut',
        data: {
          labels: ['Mobiltelefon', 'Desktop', 'Tablet'],
          datasets: [
            {
              data: [58, 36, 6],
              backgroundColor: ['#2563eb', '#10b981', '#f59e0b'],
              borderWidth: 3,
              borderColor: '#ffffff',
              hoverOffset: 4
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '72%',
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: '#0f172a',
              padding: 8,
              cornerRadius: 6,
              callbacks: {
                label: function(context) {
                  return ` ${context.label}: ${context.parsed}%`;
                }
              }
            }
          }
        }
      });
    }
  }

  // =========================================================================
  // OPDATERING AF KONTROLPANEL VED DATOINTERVALSKIFT
  // =========================================================================

  function opdaterDataInterval(intervalNøgle) {
    const valgt = dataSæt[intervalNøgle] || dataSæt["7days"];

    // Opdater statistikkort med blød numerisk animation
    animerTal(todayVisitsElem, valgt.besoeg);
    animerTal(pageviewsElem, valgt.sidevisninger);
    avgDurationElem.textContent = valgt.gnsVarighed;

    // Opdater diagrammer
    if (visitorChartInstance) {
      visitorChartInstance.data.labels = valgt.linjeLabels;
      visitorChartInstance.data.datasets[0].data = valgt.linjeDataNu;
      visitorChartInstance.data.datasets[1].data = valgt.linjeDataFoer;
      visitorChartInstance.update('active');
    }

    if (dailyVisitsChartInstance) {
      dailyVisitsChartInstance.data.labels = valgt.soejleLabels;
      dailyVisitsChartInstance.data.datasets[0].data = valgt.soejleData;
      dailyVisitsChartInstance.update('active');
    }

    lastUpdatedNotice.textContent = `Sidst opdateret: ${getDanskTidspunkt()}`;
    visToast(`Data opdateret for: ${valgt.navn}`);
  }

  dateRangeSelect.addEventListener('change', (e) => {
    opdaterDataInterval(e.target.value);
  });

  // Hjælpefunktion til talanimation
  function animerTal(element, slutVærdi) {
    element.classList.add('number-flash');
    element.textContent = formatTal(slutVærdi);
    setTimeout(() => {
      element.classList.remove('number-flash');
    }, 600);
  }

  // =========================================================================
  // OPDATER KNAP INTERAKTION
  // =========================================================================

  refreshBtn.addEventListener('click', () => {
    refreshBtn.classList.add('is-spinning');
    
    // Simuler hurtigt netværksrespons
    setTimeout(() => {
      refreshBtn.classList.remove('is-spinning');
      const valgtInterval = dateRangeSelect.value;
      const data = dataSæt[valgtInterval];

      // Små tilfældige justeringer for at demonstrere friskhed
      const ekstraBesoeg = Math.floor(Math.random() * 8) + 1;
      data.besoeg += ekstraBesoeg;
      data.sidevisninger += ekstraBesoeg * 3;
      
      animerTal(todayVisitsElem, data.besoeg);
      animerTal(pageviewsElem, data.sidevisninger);

      // Tilføj en ny sidevisning til tabellen
      genererNySidevisning(true);

      lastUpdatedNotice.textContent = `Sidst opdateret: ${getDanskTidspunkt()}`;
      visToast(`Kontrolpanel opdateret kl. ${getDanskTidspunkt()}`);
    }, 450);
  });

  // =========================================================================
  // RENDERING & FILTRERING AF TABEL
  // =========================================================================

  function renderTabel() {
    tableBody.innerHTML = '';

    // Filtrér baseret på aktiv chip og søgefelt
    const filtreredeRækker = tabelData.filter(item => {
      const matcherStatus = (aktivtFilter === "alle") || (item.status.label.toLowerCase() === aktivtFilter.toLowerCase());
      const matcherTekst = soegetekst === "" ||
        item.side.sti.toLowerCase().includes(soegetekst) ||
        item.side.titel.toLowerCase().includes(soegetekst) ||
        item.kilde.toLowerCase().includes(soegetekst) ||
        item.status.label.toLowerCase().includes(soegetekst);
      return matcherStatus && matcherTekst;
    });

    if (filtreredeRækker.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="5" style="text-align:center; padding: 32px; color: var(--text-muted);">
            Ingen sidevisninger matcher det valgte filter eller søgeord.
          </td>
        </tr>
      `;
      tableRowCountElem.textContent = "Viser 0 hændelser";
      return;
    }

    filtreredeRækker.forEach((item, index) => {
      const tr = document.createElement('tr');
      if (item.erNy) {
        tr.classList.add('row-new');
        // Fjern 'erNy' flag efter første visning
        delete item.erNy;
      }

      tr.innerHTML = `
        <td>
          <div class="page-info">
            <span class="page-path">${item.side.sti}</span>
            <span class="page-title-text">${item.side.titel}</span>
          </div>
        </td>
        <td class="timestamp-col">${item.tidspunkt}</td>
        <td>${item.kilde}</td>
        <td>
          <span class="device-tag">
            ${item.enhed.icon}
            ${item.enhed.type}
          </span>
        </td>
        <td>
          <span class="status-badge ${item.status.klasse}">
            ${item.status.label}
          </span>
        </td>
      `;
      tableBody.appendChild(tr);
    });

    tableRowCountElem.textContent = `Viser ${filtreredeRækker.length} af ${tabelData.length} registrerede hændelser`;
  }

  // Søgning i tabel
  tableSearchInput.addEventListener('input', (e) => {
    soegetekst = e.target.value.trim().toLowerCase();
    renderTabel();
  });

  // Filter chips i tabel
  filterChips.forEach(chip => {
    chip.addEventListener('click', () => {
      filterChips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      aktivtFilter = chip.dataset.filter;
      renderTabel();
    });
  });

  // Nulstil log knap
  clearTableLogBtn.addEventListener('click', () => {
    tabelData = tabelData.slice(0, 5);
    renderTabel();
    visToast("Tabel log nulstillet");
  });

  // Hent flere rækker knap
  loadMoreRowsBtn.addEventListener('click', () => {
    for (let i = 0; i < 4; i++) {
      genererNySidevisning(false, true);
    }
    renderTabel();
    visToast("Hentede 4 yderligere historiske sidevisninger");
  });

  // =========================================================================
  // REAL-TIME SIMULATION AF BESØGENDE & HÆNDELSER
  // =========================================================================

  function genererNySidevisning(markerNy = true, laegIBunden = false) {
    const tilfældigSide = siderBibliotek[Math.floor(Math.random() * siderBibliotek.length)];
    const tilfældigKilde = kilderBibliotek[Math.floor(Math.random() * kilderBibliotek.length)];
    const tilfældigEnhed = enhederBibliotek[Math.floor(Math.random() * enhederBibliotek.length)];
    const tilfældigStatus = statusBibliotek[Math.floor(Math.random() * statusBibliotek.length)];

    const nytElement = {
      side: tilfældigSide,
      tidspunkt: "Lige nu",
      kilde: tilfældigKilde,
      enhed: tilfældigEnhed,
      status: tilfældigStatus,
      erNy: markerNy
    };

    if (laegIBunden) {
      nytElement.tidspunkt = `For ${Math.floor(Math.random() * 20) + 10} min. siden`;
      tabelData.push(nytElement);
    } else {
      // Ældre rækker opdaterer deres tidstekst
      if (tabelData[0]) tabelData[0].tidspunkt = "For 15 sek. siden";
      if (tabelData[1]) tabelData[1].tidspunkt = "For 45 sek. siden";
      if (tabelData[2]) tabelData[2].tidspunkt = "For 1 min. siden";

      tabelData.unshift(nytElement);
      // Begræns tabellen til maksimalt 14 rækker
      if (tabelData.length > 14) {
        tabelData.pop();
      }
    }

    renderTabel();
  }

  async function hentLiveStats() {
    try {
      const r = await fetch('stats.php', { cache: 'no-store' });
      const d = await r.json();
      const online = Number(d.online) || 0;
      const total = Number(d.total_events) || 0;

      if (activeVisitorsElem) {
        activeVisitorsElem.textContent = String(online);
        activeVisitorsElem.classList.add('number-flash');
        setTimeout(() => activeVisitorsElem.classList.remove('number-flash'), 500);
      }
      if (activeVisitorsTrendElem) {
        activeVisitorsTrendElem.textContent = 'Live (sidste 60 sek.)';
      }
      if (todayVisitsElem) {
        todayVisitsElem.textContent = formatTal(total);
      }
      if (lastUpdatedNotice) {
        lastUpdatedNotice.textContent = `Sidst opdateret: ${getDanskTidspunkt()}`;
      }
    } catch (e) { /* ignore */ }
  }

  function startSimulation() {
    if (simulationInterval) clearInterval(simulationInterval);

    hentLiveStats();
    // Live online fra stats.php hvert 5. sekund
    simulationInterval = setInterval(() => {
      if (!isSimulating) return;
      hentLiveStats();
      // Behold let UI-bevægelse i tabellen
      if (Math.random() > 0.55) genererNySidevisning(true);
    }, 5000);
  }

  // Tænd/sluk for simulation
  toggleSimBtn.addEventListener('click', () => {
    isSimulating = !isSimulating;
    if (isSimulating) {
      toggleSimBtn.classList.add('active');
      toggleSimBtn.textContent = 'Aktiv';
      visToast("Live-simulation genoptaget");
    } else {
      toggleSimBtn.classList.remove('active');
      toggleSimBtn.textContent = 'Sat på pause';
      visToast("Live-simulation sat på pause");
    }
  });

  // =========================================================================
  // MOBIL MENU & INTERAKTIVITET
  // =========================================================================

  function aabnSidebar() {
    sidebar.classList.add('open');
    sidebarBackdrop.classList.add('active');
  }

  function lukSidebar() {
    sidebar.classList.remove('open');
    sidebarBackdrop.classList.remove('active');
  }

  if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', aabnSidebar);
  if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', lukSidebar);
  if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', lukSidebar);

  // Håndtering af aktiv fane i sidebar navigation
  const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
  navLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      
      // Hvis linket er en sektionsreference eller kontrolpanel
      if (href && (href.startsWith('#') || href === '#')) {
        e.preventDefault();
        
        // Opdater den aktive blå fremhævning til det valgte faneblad
        navLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');

        if (href === '#' || href === '') {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
          const targetElem = document.querySelector(href);
          if (targetElem) {
            targetElem.scrollIntoView({ behavior: 'smooth' });
          }
        }
      }

      if (window.innerWidth <= 900) {
        lukSidebar();
      }
    });
  });

  // Synkroniser automatisk aktiv fane med brugerens scrollposition
  window.addEventListener('scroll', () => {
    const scrollPos = window.scrollY + 140;
    const sektioner = [
      { id: '#sidevisninger-sektion', link: document.querySelector('.nav-link[href="#sidevisninger-sektion"]') },
      { id: '#kilder-sektion', link: document.querySelector('.nav-link[href="#kilder-sektion"]') },
      { id: '#trafik-sektion', link: document.querySelector('.nav-link[href="#trafik-sektion"]') }
    ];

    let fundet = false;
    for (const sek of sektioner) {
      const el = document.querySelector(sek.id);
      if (el && scrollPos >= el.offsetTop) {
        navLinks.forEach(l => l.classList.remove('active'));
        if (sek.link) sek.link.classList.add('active');
        fundet = true;
        break;
      }
    }

    if (!fundet || window.scrollY < 200) {
      navLinks.forEach(l => l.classList.remove('active'));
      const forsideLink = document.querySelector('.nav-link[data-view="dashboard"]');
      if (forsideLink) forsideLink.classList.add('active');
    }
  }, { passive: true });

  // Eksport & Indstillinger knapper
  const exportBtn = document.getElementById('exportReportBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', (e) => {
      e.preventDefault();
      visToast("Rapport generering: Demotilstand uden servereksport.");
    });
  }

  const settingsBtn = document.getElementById('settingsBtn');
  if (settingsBtn) {
    settingsBtn.addEventListener('click', (e) => {
      e.preventDefault();
      visToast("Indstillinger: Standardparametre er aktive.");
    });
  }

  // =========================================================================
  // START APPLICATION
  // =========================================================================

  initCharts();
  renderTabel();
  startSimulation();

  // Opdater diagrammer når fonte er færdigindlæst så tal måles 100% præcist
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(() => {
      if (visitorChartInstance) visitorChartInstance.update('none');
      if (dailyVisitsChartInstance) dailyVisitsChartInstance.update('none');
      if (deviceChartInstance) deviceChartInstance.update('none');
    });
  }
});
