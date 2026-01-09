const spruecheMap = new Map();

spruecheMap.set('regnerisch', `Hüt isches
am regne!`);
spruecheMap.set('sonnig', `Hüt isches
sonnig!`);
spruecheMap.set('bewölkt', `Hüt isches
bewölkt!`);
spruecheMap.set('teilweise bewölkt', `Hüt isches
bewölkt!`);
spruecheMap.set('schneit', `Hüt isches
am schneeie!`);
spruecheMap.set('neblig', `Hüt isches
neblig!`);
spruecheMap.set('gewitter', `Hüt isches
am gwittere!`);

const datumWaehler = document.querySelector('#datum');
const temperaturAnzeige = document.querySelector('#temperatur-anzeige');
const backBtn = document.querySelector('#backBtn');



// datumWahler auf heute setzen
// 
const heute = new Date();

if (heute.getFullYear() === 2025) {
  datumWaehler.value = heute.toISOString().split('T')[0];
} else {
  datumWaehler.value = '2025-01-01';
}

// Remove ortWaehler and add event listeners for city buttons
const cityButtons = document.querySelectorAll('.city-button');

cityButtons.forEach(button => {
    button.addEventListener('click', async () => {
        const selectedCity = button.dataset.ort;
        const daten = await datenLaden();
        anzeigenDaten(daten, selectedCity, datumWaehler.value);
        
    });
});

// datumWaehler remains unchanged
// datumWaehler.addEventListener('change', async () => {
//     console.log(datumWaehler.value);
//     const daten = await datenLaden();
//     const selectedCity = document.querySelector('.city-button.active')?.dataset.ort || 'Bern'; // Default to Bern
//     anzeigenDaten(daten, selectedCity, datumWaehler.value);
// });



// Fix the syntax error in the 'temperatur' ID and rename the function to avoid conflict
function ortHandler(city) {
  const wetterAnzeige = document.getElementById('wetter-anzeige');
  const temperaturAnzeige = document.getElementById('temperatur');
  }



/////
//Daten laden und anzeigen
//_____________________________
///

// Hier werden die Daten von der DB geladen, damit wir damit arbeiten können.
async function datenLaden() {
    const response = await fetch('https://im3hs25.lorenaritschard.ch/php/unload.php');
    const daten = await response.json();
    return daten;
}

// Hier werden die Daten ins DOM (Frontend) geladen, damit der User sie sieht.

function anzeigenDaten(daten, ort, datum) {
    const temperaturAnzeigeText = document.getElementById('temperatur-anzeige-text');
    const wetterAnzeigeText = document.getElementById('wetter-anzeige-text');
    const cityNameDisplay = document.getElementById('cityName');
    const weatherDateDisplay = document.getElementById('weatherDate');
    
  


    // Filter data for the selected city and date
    const wetterDaten = daten
        .filter(eintrag => eintrag.ort === ort && eintrag.timestamp.startsWith(datum))
        .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp)); // Sort by timestamp descending

    if (wetterDaten.length > 0) {
        const aktuell = wetterDaten[0]; // Most recent entry
        
        cityNameDisplay.textContent = ort;
        weatherDateDisplay.textContent = new Date(aktuell.timestamp).toLocaleString('de-CH', {
            day: '2-digit',
            month: 'long', // This will display the month in full German text, e.g., "Januar"
            year: '2-digit',
        });

    

        temperaturAnzeigeText.textContent = `${aktuell.temperatur} °C`;

        wetterAnzeigeText.textContent = spruecheMap.get(aktuell.weather_code) || 'Wetter unbekannt';

        

        const weatherCard = document.getElementById('weather-card');
        weatherCard.className = '';
        weatherCard.classList.add(aktuell.weather_code);
        weatherCard.style.display = 'block'; 
        document.querySelector('.container').style.display = 'none'; // Startseite ausblenden
        

        
        

    } else {
        // temperaturAnzeige.textContent = 'Keine Daten verfügbar';
        temperaturAnzeigeText.textContent = 'Keine Daten verfügbar';
    }  
}



// "Zurück"-Button Funktion
backBtn.addEventListener('click', () => {
    document.getElementById('weather-card').style.display = 'none'; // Detailseite ausblenden
    document.querySelector('.container').style.display = 'flex'; // Startseite ausblenden
});



