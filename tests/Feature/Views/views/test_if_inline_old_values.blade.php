<select
        id="product_category"
        name="product_category"
        required
        class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
    <option value="" {{ old('product_category') == '' ? 'selected' : '' }}>Selecione uma categoria</option>
    <option value="eletronico" {{ old('product_category') == 'eletronico' ? 'selected' : '' }}>Eletrônicos</option>
    <option value="moda" {{ old('product_category') == 'moda' ? 'selected' : '' }}>Moda e Vestuário</option>
    <option value="alimentos" {{ old('product_category') == 'alimentos' ? 'selected' : '' }}>Alimentos e Bebidas</option>
    <option value="moveis" {{ old('product_category') == 'moveis' ? 'selected' : '' }}>Móveis</option>
    <option value="beleza" {{ old('product_category') == 'beleza' ? 'selected' : '' }}>Beleza e Higiene</option>
    <option value="automovel" {{ old('product_category') == 'automovel' ? 'selected' : '' }}>Automóvel</option>
    <option value="artesanato" {{ old('product_category') == 'artesanato' ? 'selected' : '' }}>Artesanato</option>
    <option value="livros" {{ old('product_category') == 'livros' ? 'selected' : '' }}>Livros</option>
    <option value="outro" {{ old('product_category') == 'outro' ? 'selected' : '' }}>Outro</option>
</select>
