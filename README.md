Metermaid
---------
Metermaid is a WordPress plugin that creates a dashboard for tracking water meter readings.

Install it, then open the "Metermaid" menu item and add your various meters.

![The two main forms on the Metermaid dashboard: "Add reading" and "Settings"](screenshots/dashboard.png)

Once you enter meter readings, you will see charts showing gallons per day compared across years and gallons YTD compared across years.

![A chart showing gallons per day compared across the years 2007-2024](screenshots/gpd.png)

![A chart showing YTD water usage compared across the years 2007-2024](screenshots/ytd.png)

Scroll down, and you'll see all previous readings.

![A table of meter readings, showing date, reading, real reading, gallons per day since last (with a minimum time period), and gallons since last reading](screenshots/readings.png)

What are child meters and parent meters?
----------------------------------------
A child meter is a meter that only counts usage that is already counted by a meter further upstream (the parent meter).

Imagine you have a well. At the well, there is a pump, and the pump pushes water to three houses. Directly after the pump, there is a meter that counts all gallons that the pump outputs. This is the parent meter. The three house meters are child meters. Their combined readings should equal the reading of the parent meter.

When you set up child and parent meters and then enter readings for all meters in a system on the same days, Metermaid will calculate how much water might be getting lost in the system, either through leaks or unmetered usage.

What is a supplement?
---------------------
Supplements are a way to track water that is added to a system between a parent and a child meter.

Imagine you have a well, and a pump pumps water from the well into a holding tank, from which three houses draw. Your well pump breaks, so you have water delivered directly into your holding tank. Without tracking this supplementary water, it will appear that the houses have used more water than the well provided.

What is the difference between a reading and a "real reading"?
--------------------------------------------------------------
Imagine you have a water meter that reads "999999". The next day, you check the meter, and it reads "000001". Even though the meter shows a single gallon, its real reading is actually 1,000,001 gallons. Metermaid automatically calculates what the true reading of a meter is based on previous readings.

Contact me with any questions: cfinke@gmail.com.