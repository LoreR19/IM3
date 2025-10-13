const datumWaehler = document.querySelector('#datum');
const temperaturAnzeige = document.querySelector('#temperatur-anzeige');

document.addEventListener('DOMContentLoaded', () => {
    document.body.style.backgroundImage = "url('homepage.svg')";
    document.body.style.backgroundSize = "cover";
    document.body.style.backgroundPosition = "center";
});

// datumWahler auf heute setzen
// 
const heute = new Date().toISOString().split('T')[0];
datumWaehler.value = heute;

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
datumWaehler.addEventListener('change', async () => {
    console.log(datumWaehler.value);
    const daten = await datenLaden();
    const selectedCity = document.querySelector('.city-button.active')?.dataset.ort || 'Bern'; // Default to Bern
    anzeigenDaten(daten, selectedCity, datumWaehler.value);
});



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
    // Filter data for the selected city and date
    const wetterDaten = daten
        .filter(eintrag => eintrag.ort === ort && eintrag.timestamp.startsWith(datum))
        .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp)); // Sort by timestamp descending

    if (wetterDaten.length > 0) {
        const aktuell = wetterDaten[0]; // Most recent entry
        temperaturAnzeige.textContent = `${aktuell.temperatur} °C (${aktuell.weather_code})`;
    } else {
        temperaturAnzeige.textContent = 'Keine Daten verfügbar';
    }
}
