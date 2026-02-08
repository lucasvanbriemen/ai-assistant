import api from "./api.js";

export default {
  themeUrl: "https://components.lucasvanbriemen.nl/api/colors?theme=THEME_NAME",
  selectedTheme: "auto",

  custom_colors: [

  ],

  getTheme() {
    if (this.selectedTheme === "auto") {
      const darkModeMediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
      return darkModeMediaQuery.matches ? "dark" : "light";
    }

    return this.selectedTheme;
  },

  async applyTheme() {
    const theme = this.getTheme();
    document.documentElement.setAttribute("data-theme", theme);

    const url = this.themeUrl.replace("THEME_NAME", theme);
    const colors = await api.get(url);

    colors.forEach(color => {
      document.documentElement.style.setProperty(`--${color.name}`, color.value);
    });

    this.custom_colors.forEach(color => {
      const name = `--${color.name}`;
      const value = theme === "dark" ? color.dark : color.light;
      document.documentElement.style.setProperty(name, value);
    });
  },
};
