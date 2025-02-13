document.addEventListener('DOMContentLoaded', () => {
    const dogResults = document.getElementById('dogResults');
    const fetchBreedsBtn = document.getElementById('fetchBreeds');

    async function fetchDogData(endpoint, params = {}) {
        try {
            const response = await fetch('/api/dogs', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    endpoint,
                    params,
                })
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            return await response.json();
        } catch (error) {
            dogResults.textContent = `Error: ${error.message}`;
            return null;
        }
    }

    async function displayRandomBreeds() {
        dogResults.innerHTML = 'Loading...';

        const breeds = await fetchDogData('/breeds', {
            limit: 15,
            page: Math.floor(Math.random() * 14)
        });
// console.log(breeds);
        if (breeds && breeds.length) {
            dogResults.innerHTML = breeds.map(breed => `
        <div>
          <h2>${breed.name}</h2>
          <p>Breed Group: ${breed.breed_group || 'Unknown'}</p>
          <p>Temperament: ${breed.temperament}</p>
          <img src="${breed.image?.url || ''}" alt="${breed.name}">
        </div>
      `).join('');
        }
    }

    fetchBreedsBtn.addEventListener('click', displayRandomBreeds);
});
