<%-- A ServicePage as a card; the whole card is the link. Scope: the ServicePage. Pass Heading (h2-h6) --%>
<article class="card group relative h-full overflow-hidden border border-base-300 bg-base-100 shadow-sm transition-shadow hover:shadow-md">
    <% if $ServiceImage %>
        <figure class="aspect-[4/3] overflow-hidden bg-base-200">
            <% with $ServiceImage.FocusFill(800, 600) %>
                <img src="$URL" alt="" width="$Width" height="$Height" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
            <% end_with %>
        </figure>
    <% end_if %>
    <div class="card-body gap-3">
        <{$Heading} class="card-title text-xl">
            <a href="$Link" class="after:absolute after:inset-0 after:rounded-box focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-primary">$Title.XML</a>
        </{$Heading}>
        <% if $Summary %><p class="opacity-80">$Summary.XML</p><% end_if %>
        <span class="mt-auto inline-flex items-center gap-2 pt-2 font-semibold text-primary" aria-hidden="true">
            <%t Antlion\\SilverwindSite\\Elements\\ElementServices.LearnMore 'Learn more' %>
            <svg class="size-4 transition-transform group-hover:translate-x-1" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
        </span>
    </div>
</article>
