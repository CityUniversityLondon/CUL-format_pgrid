= Pearson Grid Course Format

This is commissioned by Pearson for City, University of London.

It started as a copy of City's culcourse course format, and incorporates
some of the rendering code from City's cul_boost theme. In particular to
cope with the rendering of the Quick Links/Activities dashboard.

This is normally rendered by the theme as part of the columns2 layout, but
only if culcourse is the course format. Consequently the dashboard rendering
code was moved into the pgrid course format and renders in about the same
place. There is a slight difference. The space used for showing the Course
and System admin menus is under the dashboard in City's theme, but is beside
the dashboard in the pgrid course format (due to how the columns2 layout
works).
