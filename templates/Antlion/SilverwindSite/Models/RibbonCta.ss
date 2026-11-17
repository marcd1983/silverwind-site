<%--
    A Ribbon CTA band (App\Models\RibbonCta::RenderedFor()). $FullBleed: spans
    the screen with content in the site container (footer ribbon, full-width
    block); otherwise a rounded box. Background/overlay/tone come from
    silverwind-blocks' HasAppearance; no colour or image means primary.
--%>
<section class="relative isolate overflow-hidden<% if not $FullBleed %> rounded-box<% end_if %><% if $UsesDefaultBackground %> bg-primary text-primary-content sw-tone-light<% else %> $ToneClass<% end_if %>"<% if $BackgroundStyle %> style="$BackgroundStyle"<% end_if %><% if $Title && not $HideTitle %> aria-labelledby="ribbon-{$ID}-title"<% else %> aria-label="$Title.ATT"<% end_if %>>
    <% if $BackgroundImage %>
        <% with $BackgroundImage.ScaleMaxWidth(2400) %>
            <picture>
                <% if $Up.MobileBackgroundImage %><source media="(max-width: 767px)" srcset="$Up.MobileBackgroundImage.ScaleMaxWidth(1200).URL"><% end_if %>
                <% with $Convert('webp') %><source srcset="$URL" type="image/webp"><% end_with %>
                <img src="$URL" width="$Width" height="$Height" alt="" loading="lazy" class="absolute inset-0 -z-20 size-full object-cover" style="object-position: $Up.BackgroundPosition">
            </picture>
        <% end_with %>
    <% end_if %>
    <% if $OverlayStyle %><div class="absolute inset-0 -z-10" style="$OverlayStyle" aria-hidden="true"></div><% end_if %>

    <div class="<% if $FullBleed %>sw-container<% else %>px-6 sm:px-10<% end_if %> <% if $Size == 'sm' %>py-8<% else_if $Size == 'lg' %>py-16 sm:py-24<% else %>py-12 sm:py-16<% end_if %>">
        <div class="flex flex-col gap-6<% if $Layout == 'split' %> lg:flex-row lg:items-center lg:justify-between lg:gap-12<% else %> $TextAlignClass<% end_if %>">
            <div class="max-w-2xl<% if $Layout == 'stacked' %> $BoxPositionClass<% end_if %>">
                <% if $Title && not $HideTitle %>
                    <h2 id="ribbon-{$ID}-title" class="text-2xl font-bold tracking-tight text-balance sm:text-3xl">$Title.XML</h2>
                <% end_if %>
                <% if $Content %><div class="prose mt-3 max-w-none<% if $Size != 'sm' %> sm:prose-lg<% end_if %>">$Content</div><% end_if %>
            </div>
            <% if $Links %>
                <div class="flex shrink-0 flex-wrap gap-3<% if $Layout == 'stacked' %> $ContentJustifyClass<% end_if %>">
                    <% loop $Links %><% include Includes/SilverwindBlocks/Button Size='btn-lg' %><% end_loop %>
                </div>
            <% end_if %>
        </div>
    </div>
</section>
