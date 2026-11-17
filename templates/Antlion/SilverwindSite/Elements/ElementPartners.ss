<%-- Partners as a logo strip or cards. A partner without a logo shows its name --%>
<% if $RenderedTitle || $Intro %>
    <div class="<% if $IsFullBleed %>sw-container<% end_if %><% if $Display == 'logos' %> text-center<% end_if %>">
        $RenderedTitle
        <% if $Intro %><div class="prose mb-6 max-w-none<% if $Display == 'logos' %> mx-auto<% end_if %>">$Intro</div><% end_if %>
    </div>
<% end_if %>
<% if $PartnerList %>
    <ul class="$GridClass<% if $Display == 'logos' %> items-center<% end_if %><% if $IsFullBleed %> sw-container<% end_if %>" role="list">
        <% loop $PartnerList %>
            <li class="h-full">
                <% if $Up.Display == 'cards' %>
                    <article class="card h-full border border-base-300 bg-base-100 shadow-sm">
                        <figure class="flex h-28 items-center justify-center bg-base-200 px-6">
                            <% if $Image %>
                                <% with $Image.FitMax(320, 96) %><img src="$URL" alt="" width="$Width" height="$Height" loading="lazy" class="max-h-16 w-auto object-contain"><% end_with %>
                            <% else %>
                                <span class="text-lg font-bold tracking-tight opacity-70" aria-hidden="true">$Title.XML</span>
                            <% end_if %>
                        </figure>
                        <div class="card-body gap-2">
                            <{$Up.CardHeading} class="card-title text-lg">$Title.XML</{$Up.CardHeading}>
                            <% if $Summary %><p class="text-sm opacity-80">$Summary.XML</p><% end_if %>
                            <% if $Links %>
                                <div class="card-actions mt-2">
                                    <% loop $Links %><% include Includes/SilverwindBlocks/Button Size='btn-sm' %><% end_loop %>
                                </div>
                            <% end_if %>
                        </div>
                    </article>
                <% else %>
                    <%-- Logo, linked to the partner's first link if it has one --%>
                    <% if $Links.First && not $Links.First.ModalTarget %><a href="$Links.First.URL" class="group block rounded-box focus-visible:outline-2 focus-visible:outline-offset-2"<% if $Links.First.OpenInNew %> target="_blank" rel="noopener noreferrer"<% end_if %>><% else %><div class="group"><% end_if %>
                        <div class="flex h-20 items-center justify-center px-4 transition<% if $Up.Greyscale %> opacity-70 grayscale group-hover:opacity-100 group-hover:grayscale-0<% end_if %>">
                            <% if $Image %>
                                <% with $Image.FitMax(320, 96) %><img src="$URL" alt="$Up.Title.ATT" width="$Width" height="$Height" loading="lazy" class="max-h-14 w-auto object-contain"><% end_with %>
                            <% else %>
                                <span class="text-center text-lg font-bold tracking-tight">$Title.XML</span>
                            <% end_if %>
                        </div>
                    <% if $Links.First && not $Links.First.ModalTarget %></a><% else %></div><% end_if %>
                <% end_if %>
            </li>
        <% end_loop %>
    </ul>
<% end_if %>
