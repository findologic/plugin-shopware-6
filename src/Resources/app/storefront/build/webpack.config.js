const { join, resolve } = require('path');

module.exports = () => {
    return {
        resolve: {
            alias: {
                '@nouislider': resolve(
                    join(__dirname, '..', 'node_modules', 'nouislider')
                )
            },
            extensions: ['.js', '.css']
        },
        module: {
            rules: [
                {
                    test: /\.css$/i,
                    use: ['style-loader', 'css-loader'],
                }
            ]
        }
    }
}
