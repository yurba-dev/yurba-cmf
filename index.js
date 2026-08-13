hljs.highlightAll()

// highlight the sidebar link for the section currently in view
const links = document.querySelectorAll('.sidebar a')
const spy = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return
        links.forEach(a => a.classList.toggle('is-active', a.getAttribute('href') == '#' + entry.target.id))
    })
}, { rootMargin: '-52px 0px -70% 0px' })
document.querySelectorAll('main section[id]').forEach(section => spy.observe(section))
