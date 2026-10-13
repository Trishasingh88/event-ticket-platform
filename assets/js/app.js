const searchInput =
    document.getElementById("searchInput");

const cards =
    document.querySelectorAll(".event-card");

const eventCount =
    document.getElementById("eventCount");


let debounceTimer;


searchInput.addEventListener(
    "input",
    function () {

        clearTimeout(debounceTimer);


        debounceTimer =
            setTimeout(() => {

                const query =
                    this.value
                        .trim()
                        .toLowerCase();


                let visible = 0;


                cards.forEach(card => {

                    const data =
                        card.dataset.search;


                    if (
                        data.includes(query)
                    ) {

                        card.style.display =
                            "block";

                        visible++;

                    }

                    else {

                        card.style.display =
                            "none";

                    }

                });


                eventCount.innerText =
                    `${visible} Events`;

            }, 300);

    }
);